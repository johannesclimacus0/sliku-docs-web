<?php

declare(strict_types=1);

namespace App\Storage;

use App\Exceptions\ValidationException;
use Psr\Http\Message\UploadedFileInterface;

class ImageUploadValidator
{
    public const int MAX_BYTES = 2 * 1024 * 1024;

    /** @return array{contents: string, extension: string} */
    public function validate(UploadedFileInterface $file): array
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            $this->fail(in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'Максимальный размер аватара — 2 МиБ'
                : 'Не удалось загрузить файл. Выберите его заново');
        }
        if (($file->getSize() ?? 0) > self::MAX_BYTES) {
            $this->fail('Максимальный размер аватара — 2 МиБ');
        }

        $stream = $file->getStream();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $contents = '';
        while (!$stream->eof() && strlen($contents) <= self::MAX_BYTES) {
            $chunk = $stream->read(min(8192, self::MAX_BYTES + 1 - strlen($contents)));
            if ($chunk === '') {
                break;
            }
            $contents .= $chunk;
        }
        if (strlen($contents) > self::MAX_BYTES) {
            $this->fail('Максимальный размер аватара — 2 МиБ');
        }

        $image = @getimagesizefromstring($contents);
        $types = [IMAGETYPE_JPEG => ['image/jpeg', 'jpg'], IMAGETYPE_PNG => ['image/png', 'png'], IMAGETYPE_WEBP => ['image/webp', 'webp']];
        $type = $image === false ? null : ($types[$image[2]] ?? null);
        if ($type === null || new \finfo(FILEINFO_MIME_TYPE)->buffer($contents) !== $type[0]) {
            $this->fail('Выберите изображение JPEG, PNG или WebP');
        }
        if ($image[0] < 1 || $image[1] < 1 || $image[0] > 4096 || $image[1] > 4096) {
            $this->fail('Размер изображения должен быть не больше 4096 × 4096');
        }

        return ['contents' => $contents, 'extension' => $type[1]];
    }

    private function fail(string $message): never
    {
        throw new ValidationException(['avatar' => [$message]]);
    }
}
