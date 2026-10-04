<?php

namespace App\Domain\Delivery;

use App\Models\Release;
use App\Models\Track;
use Illuminate\Support\Str;
use ZipStream\CompressionMethod;
use ZipStream\OperationMode;
use ZipStream\ZipStream;

/**
 * Yayının teslim paketi: orijinal ses dosyaları, kapak, metadata.json ve
 * metadata.xlsx. ZIP diske yazılmaz; çıktı akışına parça parça yazılır. Ses
 * dosyaları zaten sıkıştırılmış ya da az sıkışan veriler olduğu için STORE
 * kullanılır; böylece büyük dosyalarda CPU harcanmaz. Çıktı PHP'nin çıktı
 * tamponu doldukça istemciye gider; bellekte biriktirilmez.
 */
class ReleaseArchive
{
    public function __construct(private readonly Release $release)
    {
        $this->release->loadMissing(ReleaseMetadata::RELATIONS);
    }

    public function fileName(): string
    {
        $artist = $this->release->artistLine();
        $parts = array_filter([$this->release->upc, $artist !== '' ? $artist : null, $this->release->displayTitle()]);
        $name = ReleaseMetadata::safeName(implode(' - ', $parts));

        return (preg_replace('/[^A-Za-z0-9 ._()-]/', '_', Str::ascii($name)) ?: $this->release->ulid).'.zip';
    }

    /**
     * @param  resource|null  $output  Boşsa php://output
     * @return list<string> Pakete eklenen dosya adları
     */
    public function stream($output = null): array
    {
        $metadata = new ReleaseMetadata($this->release);
        $zip = new ZipStream(
            operationMode: OperationMode::NORMAL,
            outputStream: $output,
            defaultCompressionMethod: CompressionMethod::STORE,
            enableZip64: true,
            defaultEnableZeroHeader: true,
            sendHttpHeaders: false,
        );
        $entries = [];

        $cover = $this->release->cover;

        if ($cover !== null && is_file($cover->absolutePath())) {
            $zip->addFileFromPath((string) $metadata->coverFileName(), $cover->absolutePath());
            $entries[] = (string) $metadata->coverFileName();
        }

        foreach ($this->release->tracks as $track) {
            /** @var Track $track */
            $name = $metadata->audioFileName($track);

            if ($name !== null && is_file($track->audio->absolutePath())) {
                $zip->addFileFromPath($name, $track->audio->absolutePath());
                $entries[] = $name;
            }
        }

        $zip->addFile('metadata.json', $metadata->toJson(), compressionMethod: CompressionMethod::DEFLATE);
        $entries[] = 'metadata.json';

        $xlsx = tempnam(sys_get_temp_dir(), 'hm-meta-');

        try {
            $metadata->writeXlsx($xlsx);
            $zip->addFileFromPath('metadata.xlsx', $xlsx);
            $entries[] = 'metadata.xlsx';
            $zip->finish();
        } finally {
            @unlink($xlsx);
        }

        return $entries;
    }
}
