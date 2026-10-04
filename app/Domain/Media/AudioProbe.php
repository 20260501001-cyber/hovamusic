<?php

namespace App\Domain\Media;

interface AudioProbe
{
    /**
     * Dosya okunamazsa null döner.
     */
    public function probe(string $absolutePath): ?ProbeResult;
}
