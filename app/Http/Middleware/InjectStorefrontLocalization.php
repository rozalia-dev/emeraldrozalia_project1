<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InjectStorefrontLocalization
{
    private const GA_UI = [
        'HOME' => 'BAILE',
        'SHOP' => 'SIOPA',
        'SHOP ALL' => 'SIOPA UILE',
        'COLLECTIONS' => 'BAILIÚCHÁIN',
        'VIEW ALL COLLECTIONS' => 'FÉACH GACH BAILIÚCHÁN',
        'CATALOGUE' => 'CATALÓG',
        'NEW ARRIVALS' => 'NUA-THAGTHA',
        'CORPORATE ORDER' => 'ORDÚ CORPARÁIDEACH',
        'BULK ORDER' => 'MÓRORDÚ',
        'FRANCHISE APPLY' => 'IARRATAS SAINCHEADÚNAIS',
        'HIRING APPLY' => 'IARRATAS POIST',
        'CONTACT US' => 'DÉAN TEAGMHÁIL',
        'ALL PRODUCTS' => 'GACH TÁIRGE',
        'TRADITIONAL' => 'TRAIDISIÚNTA',
        'HERITAGE' => 'OIDHREACHT',
        'CLASSIC' => 'CLASAICEACH',
        'OUTDOOR' => 'LASMUIGH',
        'WINTER' => 'GEIMHREADH',
        'SPORTS' => 'SPÓRT',
        'WORKWEAR' => 'ÉADAÍ OIBRE',
        'KIDS' => 'PÁISTÍ',
        'COSTUME' => 'FEISTEAS',
        'FILTERS' => 'SCAGAIRÍ',
        'RESET ALL' => 'ATHSHOCRIGH UILE',
        'COLOUR' => 'DATH',
        'MATERIAL' => 'ÁBHAR',
        'SIZE' => 'MÉID',
        'PRICE' => 'PRAGHAS',
        'AVAILABILITY' => 'INFHAIGHTEACHT',
        'APPLY FILTERS' => 'CUIR SCAGAIRÍ I BHFEIDHM',
        'ADD TO CART' => 'CUIR SA CHISEÁN',
        'VIEW DETAILS' => 'FÉACH SONRAÍ',
        'NEW ARRIVAL' => 'NUA-THAGTHA',
        'IN STOCK' => 'I STOC',
        'OUT OF STOCK' => 'AS STOC',
        'SEARCH' => 'CUARDAIGH',
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || $request->is('admin/*') || ! $response instanceof Response) {
            return $response;
        }
        if (! str_contains(strtolower((string) $response->headers->get('Content-Type')), 'text/html')) {
            return $response;
        }

        $html = (string) $response->getContent();
        if ($html === '' || ! str_contains($html, '</body>')) {
            return $response;
        }

        $payload = app(TenantContext::class)->storefrontPayload();
        if ($request->routeIs('order.success')) {
            $payload['rate'] = 1.0;
        }
        $payload['convert_prices'] = ! $request->routeIs('account.*');
        $payload['translations'] = $payload['locale'] === 'ga' ? self::GA_UI : [];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $script = <<<'HTML'
<script id="storefront-localization-runtime">
(() => {
    const cfg = __PAYLOAD__;
    document.documentElement.dataset.storefrontLocale = cfg.locale;
    document.documentElement.dataset.storefrontCurrency = cfg.currency;
    const excluded = new Set(['SCRIPT','STYLE','NOSCRIPT','TEXTAREA','OPTION']);
    const amountPattern = /€\s?(\d{1,3}(?:,\d{3})*(?:\.\d{1,2})?|\d+(?:\.\d{1,2})?)/g;
    const money = (raw) => {
        const numeric = Number(String(raw).replace(/,/g, ''));
        if (!Number.isFinite(numeric)) return raw;
        return cfg.symbol + (numeric * Number(cfg.rate || 1)).toLocaleString('en-IE', {minimumFractionDigits: cfg.decimals, maximumFractionDigits: cfg.decimals});
    };
    const translate = (text) => {
        if (!cfg.translations || !Object.keys(cfg.translations).length) return text;
        const trimmed = text.trim();
        if (!trimmed) return text;
        const translated = cfg.translations[trimmed.toUpperCase()];
        if (!translated) return text;
        const start = text.indexOf(trimmed);
        return text.slice(0, start) + translated + text.slice(start + trimmed.length);
    };
    const processText = (node) => {
        if (!node || !node.parentElement || excluded.has(node.parentElement.tagName)) return;
        let next = translate(node.nodeValue || '');
        if (cfg.convert_prices) next = next.replace(amountPattern, (_, value) => money(value));
        if (next !== node.nodeValue) node.nodeValue = next;
    };
    const walk = (root) => {
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        let node;
        while ((node = walker.nextNode())) processText(node);
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
