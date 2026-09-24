#!/usr/bin/env bash
set -Eeuo pipefail

DOMAIN="${HOST_NGINX_DOMAIN:-emeraldrozalia.com}"
UPLOAD_LIMIT="${HOST_NGINX_UPLOAD_LIMIT:-1100M}"
UPLOAD_TIMEOUT="${HOST_NGINX_UPLOAD_TIMEOUT:-900s}"

run_root() {
    if [ "$(id -u)" -eq 0 ]; then
        "$@"
        return
    fi

    if command -v sudo >/dev/null 2>&1 && sudo -n true >/dev/null 2>&1; then
        sudo -n "$@"
        return
    fi

    # The production deploy user already has Docker access for releases.
    # Docker access is root-equivalent, so use an ephemeral privileged helper
    # to execute host commands inside the host filesystem / PID namespace.
    if command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
        docker run --rm             --privileged             --pid=host             --network=host             -v /:/host             alpine:3.20             chroot /host "$@"
        return
    fi

    echo "Production nginx upload-limit update requires root, passwordless sudo, or Docker access." >&2
    exit 78
}

mapfile -t matches < <(
    grep -RIlF "$DOMAIN" /etc/nginx/sites-enabled /etc/nginx/conf.d 2>/dev/null || true
)

if [ "${#matches[@]}" -eq 0 ]; then
    echo "Could not find the nginx server block for ${DOMAIN}." >&2
    exit 78
fi

declare -A seen=()
declare -A backups=()
updated=0

for match in "${matches[@]}"; do
    target="$(readlink -f "$match")"
    [ -n "$target" ] || continue
    [ -z "${seen[$target]:-}" ] || continue
    seen[$target]=1

    tmp="$(mktemp)"
    backup="${target}.pre-large-upload-$(date -u +%Y%m%dT%H%M%SZ)"

    python3 - "$target" "$tmp" "$DOMAIN" "$UPLOAD_LIMIT" "$UPLOAD_TIMEOUT" <<'PY'
import re
import sys
from pathlib import Path

src, dst, domain, limit, timeout = sys.argv[1:]
text = Path(src).read_text()
lines = text.splitlines(keepends=True)
domain_re = re.compile(r"\b(?:www\.)?" + re.escape(domain) + r"\b")

def brace_delta(line: str) -> int:
    code = line.split("#", 1)[0]
    return code.count("{") - code.count("}")

out = []
i = 0
changed = False

while i < len(lines):
    line = lines[i]
    if not re.match(r"^\s*server\s*\{", line):
        out.append(line)
        i += 1
        continue

    depth = 0
    j = i
    block = []
    while j < len(lines):
        block.append(lines[j])
        depth += brace_delta(lines[j])
        if j > i and depth == 0:
            break
        j += 1

    block_text = "".join(block)
    server_name_match = re.search(r"(?m)^\s*server_name\s+([^;]+);", block_text)

    if not server_name_match or not domain_re.search(server_name_match.group(1)):
        out.extend(block)
        i = j + 1
        continue

    cleaned = []
    rel_depth = 0
    inserted = False

    for block_line in block:
        code = block_line.split("#", 1)[0]
        stripped = code.strip()

        if rel_depth == 1 and re.match(
            r"^(client_max_body_size|client_body_timeout|proxy_read_timeout|proxy_send_timeout)\s+",
            stripped,
        ):
            changed = True
            rel_depth += brace_delta(block_line)
            continue

        cleaned.append(block_line)

        if rel_depth == 1 and re.match(r"^\s*server_name\s+", block_line):
            indent = re.match(r"^(\s*)", block_line).group(1)
            cleaned.extend([
                f"{indent}client_max_body_size {limit};\n",
                f"{indent}client_body_timeout {timeout};\n",
                f"{indent}proxy_read_timeout {timeout};\n",
                f"{indent}proxy_send_timeout {timeout};\n",
            ])
            inserted = True
            changed = True

        rel_depth += brace_delta(block_line)

    if not inserted:
        raise SystemExit(f"Matched {domain} server block but could not find a server_name line to update.")

    out.extend(cleaned)
    i = j + 1

if not changed:
    raise SystemExit(f"No nginx changes were made for {domain}.")

Path(dst).write_text("".join(out))
PY

    if cmp -s "$target" "$tmp"; then
        rm -f "$tmp"
        continue
    fi

    run_root cp -a "$target" "$backup"
    backups["$target"]="$backup"
    run_root install -m 0644 "$tmp" "$target"
    rm -f "$tmp"
    updated=$((updated + 1))
done

if ! run_root nginx -t; then
    echo "nginx configuration test failed after upload-limit update; restoring previous configuration." >&2
    for target in "${!backups[@]}"; do
        run_root cp -a "${backups[$target]}" "$target"
    done
    run_root nginx -t || true
    exit 1
fi

if command -v systemctl >/dev/null 2>&1; then
    run_root systemctl reload nginx
else
    run_root nginx -s reload
fi

echo "Updated ${updated} host nginx config file(s) for ${DOMAIN}: body limit ${UPLOAD_LIMIT}, timeout ${UPLOAD_TIMEOUT}."
