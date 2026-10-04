<?php

return [
    'no_extension' => 'yok',
    'cover' => [
        'format' => 'Dosya JPG ya da PNG değil; kapak JPG veya PNG olmalı.',
        'unreadable' => 'Kapak okunamadı; dosya bozuk olabilir. Başka bir JPG ya da PNG dene.',
        'too_large' => 'Kapak :size; en fazla :max olmalı.',
        'dimensions' => 'Kapak :width×:height px; 3000×3000 px olmalı.',
        'not_square' => 'Kapak :width×:height px ve kare değil; 3000×3000 px olmalı.',
        'color_space' => 'Kapak :space renk uzayında; RGB olmalı.',
        'spaces' => [
            'gray' => 'gri tonlamalı',
        ],
    ],
    'audio' => [
        'extension' => 'Dosya uzantısı :ext; WAV veya FLAC yükle.',
        'empty' => 'Dosya boş; başka bir dosya seç.',
        'too_large' => 'Dosya :size; en fazla :max olmalı.',
        'not_audio' => 'Dosya WAV ya da FLAC değil; WAV veya FLAC yükle.',
        'unreadable' => 'Ses dosyası okunamadı; dosya bozuk olabilir. Yeniden dışa aktarıp yükle.',
        'format' => 'Dosya :format; WAV veya FLAC olmalı.',
        'float' => 'Ses :bits bit kayan noktalı; 16 ya da 24 bit olmalı.',
        'bit_depth' => 'Bit derinliği :bits bit; 16 ya da 24 bit olmalı.',
        'sample_rate' => 'Örnekleme hızı :rate; en az 44,1 kHz olmalı.',
        'duration' => 'Ses süresi 1 saniyeden kısa; parçanın tamamını yükle.',
        'channels' => '{1} mono|{2} stereo|[3,*] :count kanal',
    ],
    'upload' => [
        'expired' => 'Yükleme oturumu sona erdi; dosyayı yeniden seç.',
        'bad_chunk' => 'Dosyanın bir parçası eksik geldi; yükleme yeniden denenecek.',
        'failed' => 'Yükleme yapılamadı; bağlantını kontrol edip yeniden dene.',
    ],
];
