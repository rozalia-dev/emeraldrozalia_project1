FROM node:20-bookworm-slim

ENV NODE_ENV=production \
    PUPPETEER_SKIP_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        chromium \
        fonts-liberation \
        fonts-noto-color-emoji \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY package.json ./
RUN npm install --omit=dev --no-audit --no-fund \
    && npm cache clean --force

COPY server.js ./

RUN mkdir -p /data/.wwebjs_auth \
    && chown -R node:node /app /data

USER node

ENV HOST=0.0.0.0 \
    PORT=3001 \
    WHATSAPP_SESSION_PATH=/data/.wwebjs_auth \
    WHATSAPP_LEDGER_PATH=/data/.wwebjs_auth/delivery-ledger.json

EXPOSE 3001

CMD ["node", "server.js"]
