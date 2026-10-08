<?php

/*
 * Proxy (Cloudflare, yük dengeleyici) arkasında güvenilen adresler. Laravel'in
 * TrustProxies ara katmanı bu değeri istek anında okur; config önbelleğiyle de çalışır.
 * Virgülle ayrılmış IP/CIDR listesi ya da tümü için "*".
 */
return [
    'proxies' => env('TRUSTED_PROXIES') ?: null,
];
