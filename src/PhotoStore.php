<?php

declare(strict_types=1);

namespace App;

use GdImage;
use PDO;

/** Profile photos: normalised to a square JPEG and kept outside the web root. */
final class PhotoStore
{
    public const SIZE = 400;
    private const MAX_BYTES = 8 * 1024 * 1024;

    public function __construct(private readonly PDO $db, private readonly string $dir)
    {
    }

    /**
     * The photo sent with a form, as normalised JPEG bytes, or null when none was sent.
     * app.js sends a ready-made square JPEG in `photo_data`; without JavaScript the raw file arrives in `photo`.
     *
     * @throws UserError
     */
    public function fromRequest(array $post, array $files): ?string
    {
        $data = (string) ($post['photo_data'] ?? '');
        if ($data !== '') {
            if (!preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#', $data, $m)) {
                throw new UserError('La foto non è valida: riprova a scattarla o caricarla.');
            }

            return $this->normalize((string) base64_decode($m[2], true));
        }

        $file = $files['photo'] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new UserError('La foto è troppo grande: scegline una più leggera.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new UserError('Il caricamento della foto non è riuscito: riprova.');
        }

        return $this->normalize((string) file_get_contents($file['tmp_name']));
    }

    /**
     * Re-encodes any supported image as a centred square JPEG. Re-encoding also drops
     * metadata such as GPS position and anything that isn't pixels.
     *
     * @throws UserError
     */
    public function normalize(string $bytes): string
    {
        $image = strlen($bytes) > 0 && strlen($bytes) <= self::MAX_BYTES && @getimagesizefromstring($bytes)
            ? @imagecreatefromstring($bytes)
            : false;
        if (!$image) {
            throw new UserError('Questo file non è un\'immagine che possiamo usare: prova con una foto JPEG o PNG.');
        }
        $image = self::orient($image, $bytes);

        $width = imagesx($image);
        $height = imagesy($image);
        $side = min($width, $height);
        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled(
            $square, $image, 0, 0,
            intdiv($width - $side, 2), intdiv($height - $side, 2),
            self::SIZE, self::SIZE, $side, $side,
        );

        ob_start();
        imagejpeg($square, null, 85);

        return (string) ob_get_clean();
    }

    public function save(int $userId, string $jpeg): void
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0700, true);
        }
        file_put_contents($this->path($userId), $jpeg, LOCK_EX);
        $this->db->prepare('UPDATE users SET photo_version = COALESCE(photo_version, 0) + 1 WHERE id = ?')
            ->execute([$userId]);
    }

    public function delete(int $userId): void
    {
        @unlink($this->path($userId));
        $this->db->prepare('UPDATE users SET photo_version = NULL WHERE id = ?')->execute([$userId]);
    }

    public function path(int $userId): string
    {
        return $this->dir . '/' . $userId . '.jpg';
    }

    /** Phone photos are often stored sideways with an EXIF hint; apply it before cropping. */
    private static function orient(GdImage $image, string $bytes): GdImage
    {
        if (!function_exists('exif_read_data') || !str_starts_with($bytes, "\xFF\xD8")) {
            return $image;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($bytes));
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }
}
