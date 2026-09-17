<?php

namespace App\Http\Middleware;

use App\Services\{StorefrontTranslator, TenantContext};
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InjectStorefrontLocalization
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (! $request->isMethod('GET') || $request->is('admin/*') || ! $response instanceof Response) return $response;
        if (! str_contains(strtolower((string) $response->headers->get('Content-Type')), 'text/html')) return $response;

        $html = (string) $response->getContent();
        if ($html === '' || ! str_contains($html, '</body>')) return $response;

        $payload = app(TenantContext::class)->storefrontPayload();
        $payload['translations'] = app(StorefrontTranslator::class)->runtimeMap($payload['locale']);
        $payload['convert_prices'] = ! $request->routeIs('account.*') && ! $request->routeIs('order.success');
        if (! $payload['convert_prices']) $payload['rate'] = 1.0;

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $script = <<<'HTML'
<script id="storefront-localization-runtime">
(() => {
    const cfg = __PAYLOAD__;
    const normalize = (value) => String(value || '').trim().replace(/\s+/g, ' ').toLocaleUpperCase(cfg.locale || undefined);
    const escapeRegExp = (value) => String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const excluded = new Set(['SCRIPT','STYLE','NOSCRIPT','TEXTAREA','OPTION','CODE','PRE']);

    document.documentElement.lang = String(cfg.locale || 'en').replace('_', '-');
    document.documentElement.dir = cfg.direction === 'rtl' ? 'rtl' : 'ltr';
    document.documentElement.dataset.storefrontLocale = cfg.locale;
    document.documentElement.dataset.storefrontCurrency = cfg.currency;

    const syncSelectors = () => {
        const languages = new Map((cfg.languages || []).map(item => [String(item.locale), item]));
        document.querySelectorAll('select[name="locale"]').forEach((select) => {
            [...select.options].forEach((option) => {
                const item = languages.get(option.value);
                if (!item) { option.remove(); return; }
                option.textContent = `${item.native_name} (${item.locale})`;
            });
            if (languages.has(cfg.locale)) select.value = cfg.locale;
        });

        const currencies = new Map((cfg.currencies || []).map(item => [String(item.code), item]));
        document.querySelectorAll('select[name="currency"]').forEach((select) => {
            [...select.options].forEach((option) => {
                const item = currencies.get(option.value);
                if (!item) { option.remove(); return; }
                option.textContent = `${item.code} ${item.symbol} — ${item.name}`;
            });
            if (currencies.has(cfg.currency)) select.value = cfg.currency;
        });
    };

    const moneyFormatter = (() => {
        try {
            return new Intl.NumberFormat(String(cfg.locale || 'en').replace('_', '-'), {
                style: 'currency', currency: cfg.currency, minimumFractionDigits: cfg.decimals, maximumFractionDigits: cfg.decimals,
            });
        } catch (_) { return null; }
    })();
    const money = (raw) => {
        const numeric = Number(String(raw).replace(/,/g, ''));
        if (!Number.isFinite(numeric)) return raw;
        const converted = numeric * Number(cfg.rate || 1);
        if (moneyFormatter) return moneyFormatter.format(converted);
        const number = converted.toLocaleString(undefined, {minimumFractionDigits: cfg.decimals, maximumFractionDigits: cfg.decimals});
        return cfg.symbol_position === 'after' ? `${number} ${cfg.symbol}` : `${cfg.symbol}${number}`;
    };

    const baseMarkers = [cfg.base_symbol, cfg.base_currency].filter(Boolean).map(escapeRegExp);
    const amountPattern = baseMarkers.length
        ? new RegExp(`(?:${baseMarkers.join('|')})\\s?(\\d{1,3}(?:,\\d{3})*(?:\\.\\d{1,2})?|\\d+(?:\\.\\d{1,2})?)`, 'g')
        : null;

    const translate = (text) => {
        if (!cfg.translations || !Object.keys(cfg.translations).length) return text;
        const trimmed = String(text || '').trim();
        if (!trimmed) return text;
        const translated = cfg.translations[normalize(trimmed)];
        if (!translated) return text;
        const start = String(text).indexOf(trimmed);
        return String(text).slice(0, start) + translated + String(text).slice(start + trimmed.length);
    };

    const processText = (node) => {
        if (!node || !node.parentElement || excluded.has(node.parentElement.tagName)) return;
        let next = translate(node.nodeValue || '');
        if (cfg.convert_prices && amountPattern) next = next.replace(amountPattern, (_, value) => money(value));
        if (next !== node.nodeValue) node.nodeValue = next;
    };

    const processAttributes = (element) => {
        if (!(element instanceof Element) || excluded.has(element.tagName)) return;
        ['placeholder','title','aria-label'].forEach((name) => {
            if (!element.hasAttribute(name)) return;
            const before = element.getAttribute(name) || '';
            const after = translate(before);
            if (after !== before) element.setAttribute(name, after);
        });
    };

    const walk = (root) => {
        if (root instanceof Element) processAttributes(root);
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT);
        let node;
        while ((node = walker.nextNode())) {
            if (node.nodeType === Node.TEXT_NODE) processText(node);
            else processAttributes(node);
        }
    };

    const adjustPriceInputs = () => {
        if (!cfg.convert_prices || Number(cfg.rate || 1) === 1) return;
        document.querySelectorAll('input[name="min_price"],input[name="max_price"],input[data-price-filter]').forEach((input) => {
            if (input.dataset.currencyAdjusted) return;
            ['min','max','value'].forEach((key) => {
                const value = Number(input[key]);
                if (Number.isFinite(value) && input[key] !== '') input[key] = String(Math.round(value * Number(cfg.rate) * 100) / 100);
            });
            input.dataset.currencyAdjusted = '1';
            input.form?.addEventListener('submit', () => {
                const value = Number(input.value);
                if (Number.isFinite(value)) input.value = String(Math.round((value / Number(cfg.rate)) * 100) / 100);
            }, {once:true});
        });
    };

    syncSelectors();
    walk(document.body);
    adjustPriceInputs();
    new MutationObserver((mutations) => mutations.forEach((mutation) => {
        if (mutation.type === 'characterData') processText(mutation.target);
        mutation.addedNodes.forEach((node) => {
            if (node.nodeType === Node.TEXT_NODE) processText(node);
            else if (node.nodeType === Node.ELEMENT_NODE && !excluded.has(node.tagName)) walk(node);
        });
    })).observe(document.body, {subtree:true, childList:true, characterData:true});
})();
</script>
HTML;
        $script = str_replace('__PAYLOAD__', $json ?: '{}', $script);
        $response->setContent(str_replace('</body>', $script."\n</body>", $html));
        return $response;
    }
}
