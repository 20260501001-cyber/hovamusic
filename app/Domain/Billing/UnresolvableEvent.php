<?php

namespace App\Domain\Billing;

use RuntimeException;

/**
 * Olay doğrulandı ama yerel kayda bağlanamadı (kullanıcı ya da kimlik eksik). Polar'ın
 * tekrar göndermesi sonucu değiştirmeyeceği için olay hata notuyla saklanır.
 */
class UnresolvableEvent extends RuntimeException {}
