<?php
/**
 * Copyright © 2026 Xavier Sanz. All rights reserved.
 * Licensed under the MIT License. See LICENSE.txt for details.
 */
declare(strict_types=1);

namespace XavierSanz\ColorCaptcha\Model;

use Laminas\Captcha\Exception\ImageNotLoadableException;
use Laminas\Captcha\Exception\NoFontProvidedException;
use Laminas\Stdlib\ErrorHandler;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Backend\App\ConfigInterface;
use Magento\Captcha\Helper\Data;
use Magento\Captcha\Model\DefaultModel;
use Magento\Captcha\Model\ResourceModel\LogFactory;
use Magento\Framework\Math\Random;
use Magento\Framework\Session\SessionManagerInterface;

/**
 * Admin CAPTCHA image model with optional per-character colors and contrast noise.
 */
class PerCharColorDefaultModel extends DefaultModel
{
    private const XML_PATH_ENABLE = 'admin/captcha/enable_per_char_captcha';
    private const XML_PATH_TEXT_LUM_MIN = 'admin/captcha/per_char_text_brightness_min';
    private const XML_PATH_TEXT_LUM_MAX = 'admin/captcha/per_char_text_brightness_max';
    private const XML_PATH_NOISE_LUM_MIN = 'admin/captcha/per_char_noise_brightness_min';
    private const XML_PATH_NOISE_LUM_MAX = 'admin/captcha/per_char_noise_brightness_max';

    private const LUMINANCE_TRIES = 50;

    private ConfigInterface $backendConfig;

    /**
     * @param SessionManagerInterface $session
     * @param Data $captchaData
     * @param LogFactory $resLogFactory
     * @param string $formId
     * @param ConfigInterface $backendConfig
     * @param Random|null $randomMath
     * @param UserContextInterface|null $userContext
     * @throws \Laminas\Captcha\Exception\ExtensionNotLoadedException
     */
    public function __construct(
        SessionManagerInterface $session,
        Data $captchaData,
        LogFactory $resLogFactory,
        $formId,
        ConfigInterface $backendConfig,
        ?Random $randomMath = null,
        ?UserContextInterface $userContext = null
    ) {
        parent::__construct($session, $captchaData, $resLogFactory, $formId, $randomMath, $userContext);
        $this->backendConfig = $backendConfig;
    }

    /**
     * @inheritDoc
     */
    protected function generateImage($id, $word)
    {
        if (!$this->isPerCharColorEnabled()) {
            parent::generateImage($id, $word);
            return;
        }

        $font = $this->getFont();
        if (empty($font)) {
            throw new NoFontProvidedException('Image CAPTCHA requires font');
        }

        $w = $this->getWidth();
        $h = $this->getHeight();
        $fsize = $this->getFontSize();
        $imgFile = $this->getImgDir() . $id . $this->getSuffix();

        if (empty($this->startImage)) {
            $img = imagecreatetruecolor($w, $h);
        } else {
            ErrorHandler::start();
            $img = imagecreatefrompng($this->startImage);
            $error = ErrorHandler::stop();
            if (!$img || $error) {
                throw new ImageNotLoadableException(
                    "Can not load start image '{$this->startImage}'",
                    0,
                    $error
                );
            }
            $w = imagesx($img);
            $h = imagesy($img);
        }

        $bgColor = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, $w - 1, $h - 1, $bgColor);

        [$textLumMin, $textLumMax] = $this->getTextLuminanceRange();
        [$noiseLumMin, $noiseLumMax] = $this->getNoiseLuminanceRange();

        $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($chars === []) {
            parent::generateImage($id, $word);
            return;
        }

        $totalWidth = 0;
        foreach ($chars as $ch) {
            $tb = imageftbbox($fsize, 0, $font, $ch);
            $totalWidth += (int) ($tb[2] - $tb[0]);
        }

        $wordBox = imageftbbox($fsize, 0, $font, $word);
        $baselineY = (int) (($h - ($wordBox[7] - $wordBox[1])) / 2);
        $curX = (int) (($w - $totalWidth) / 2);

        foreach ($chars as $ch) {
            $textColor = $this->allocateColorForLuminance($img, $textLumMin, $textLumMax);
            imagefttext($img, $fsize, 0, $curX, $baselineY, $textColor, $font, $ch);
            $tb = imageftbbox($fsize, 0, $font, $ch);
            $curX += (int) ($tb[2] - $tb[0]);
        }

        for ($i = 0; $i < $this->dotNoiseLevel; $i++) {
            $noiseColor = $this->allocateGrayscaleForLuminance($img, $noiseLumMin, $noiseLumMax);
            imagefilledellipse($img, mt_rand(0, $w), mt_rand(0, $h), 2, 2, $noiseColor);
        }
        for ($i = 0; $i < $this->lineNoiseLevel; $i++) {
            $noiseColor = $this->allocateGrayscaleForLuminance($img, $noiseLumMin, $noiseLumMax);
            imageline($img, mt_rand(0, $w), mt_rand(0, $h), mt_rand(0, $w), mt_rand(0, $h), $noiseColor);
        }

        $img2 = imagecreatetruecolor($w, $h);
        $bgColor2 = imagecolorallocate($img2, 255, 255, 255);
        imagefilledrectangle($img2, 0, 0, $w - 1, $h - 1, $bgColor2);

        $freq1 = $this->randomFreq();
        $freq2 = $this->randomFreq();
        $freq3 = $this->randomFreq();
        $freq4 = $this->randomFreq();
        $ph1 = $this->randomPhase();
        $ph2 = $this->randomPhase();
        $ph3 = $this->randomPhase();
        $ph4 = $this->randomPhase();
        $szx = $this->randomSize();
        $szy = $this->randomSize();

        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $sxf = $x + (sin($x * $freq1 + $ph1) + sin($y * $freq3 + $ph3)) * $szx;
                $syf = $y + (sin($x * $freq2 + $ph2) + sin($y * $freq4 + $ph4)) * $szy;

                if ($sxf < 0 || $syf < 0 || $sxf >= $w - 1 || $syf >= $h - 1) {
                    continue;
                }

                $rgb = $this->bilinearRgb($img, $sxf, $syf);
                if ($rgb === null) {
                    continue;
                }

                imagesetpixel(
                    $img2,
                    $x,
                    $y,
                    imagecolorallocate($img2, $rgb[0], $rgb[1], $rgb[2])
                );
            }
        }

        for ($i = 0; $i < $this->dotNoiseLevel; $i++) {
            $noiseColor = $this->allocateGrayscaleForLuminance($img2, $noiseLumMin, $noiseLumMax);
            imagefilledellipse($img2, mt_rand(0, $w), mt_rand(0, $h), 2, 2, $noiseColor);
        }
        for ($i = 0; $i < $this->lineNoiseLevel; $i++) {
            $noiseColor = $this->allocateGrayscaleForLuminance($img2, $noiseLumMin, $noiseLumMax);
            imageline($img2, mt_rand(0, $w), mt_rand(0, $h), mt_rand(0, $w), mt_rand(0, $h), $noiseColor);
        }

        imagepng($img2, $imgFile);
    }

    private function isPerCharColorEnabled(): bool
    {
        return (string) $this->backendConfig->getValue(self::XML_PATH_ENABLE) === '1';
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function getTextLuminanceRange(): array
    {
        $min = $this->getIntConfig(self::XML_PATH_TEXT_LUM_MIN, 0);
        $max = $this->getIntConfig(self::XML_PATH_TEXT_LUM_MAX, 105);
        if ($max < $min) {
            return [$max, $min];
        }
        return [$min, $max];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function getNoiseLuminanceRange(): array
    {
        $min = $this->getIntConfig(self::XML_PATH_NOISE_LUM_MIN, 160);
        $max = $this->getIntConfig(self::XML_PATH_NOISE_LUM_MAX, 230);
        if ($max < $min) {
            [$min, $max] = [$max, $min];
        }
        [, $textMax] = $this->getTextLuminanceRange();
        if ($min <= $textMax) {
            $min = min(255, $textMax + 1);
        }
        if ($max < $min) {
            $max = $min;
        }
        return [$min, $max];
    }

    private function getIntConfig(string $path, int $default): int
    {
        $v = $this->backendConfig->getValue($path);
        if ($v === null || $v === '') {
            return $default;
        }
        return max(0, min(255, (int) $v));
    }

    /**
     * @param resource|\GdImage $image
     */
    private function allocateColorForLuminance($image, int $lumMin, int $lumMax): int
    {
        [$r, $g, $b] = $this->randomRgbForLuminance($lumMin, $lumMax);
        return imagecolorallocate($image, $r, $g, $b);
    }

    /**
     * Light gray noise only (no chroma) so glyphs keep visible hue after the wave.
     *
     * @param resource|\GdImage $image
     */
    private function allocateGrayscaleForLuminance($image, int $lumMin, int $lumMax): int
    {
        $lumMin = max(0, min(255, $lumMin));
        $lumMax = max(0, min(255, $lumMax));
        if ($lumMax < $lumMin) {
            [$lumMin, $lumMax] = [$lumMax, $lumMin];
        }
        $gray = mt_rand($lumMin, $lumMax);

        return imagecolorallocate($image, $gray, $gray, $gray);
    }

    /**
     * Bilinear sample truecolor RGB; preserves hue through wave distortion.
     *
     * @param resource|\GdImage $img
     * @return array{0: int, 1: int, 2: int}|null null when sample is white background (skip pixel)
     */
    private function bilinearRgb($img, float $sxf, float $syf): ?array
    {
        $fracX = $sxf - floor($sxf);
        $fracY = $syf - floor($syf);
        $fracX1 = 1.0 - $fracX;
        $fracY1 = 1.0 - $fracY;
        $x0 = (int) floor($sxf);
        $y0 = (int) floor($syf);

        $p00 = $this->rgbAt($img, $x0, $y0);
        $p10 = $this->rgbAt($img, $x0 + 1, $y0);
        $p01 = $this->rgbAt($img, $x0, $y0 + 1);
        $p11 = $this->rgbAt($img, $x0 + 1, $y0 + 1);

        if ($this->isRgbWhite($p00) && $this->isRgbWhite($p10) && $this->isRgbWhite($p01) && $this->isRgbWhite($p11)) {
            return null;
        }

        $newR = $p00[0] * $fracX1 * $fracY1 + $p10[0] * $fracX * $fracY1 + $p01[0] * $fracX1 * $fracY + $p11[0] * $fracX * $fracY;
        $newG = $p00[1] * $fracX1 * $fracY1 + $p10[1] * $fracX * $fracY1 + $p01[1] * $fracX1 * $fracY + $p11[1] * $fracX * $fracY;
        $newB = $p00[2] * $fracX1 * $fracY1 + $p10[2] * $fracX * $fracY1 + $p01[2] * $fracX1 * $fracY + $p11[2] * $fracX * $fracY;

        return [
            max(0, min(255, (int) round($newR))),
            max(0, min(255, (int) round($newG))),
            max(0, min(255, (int) round($newB))),
        ];
    }

    /**
     * @param resource|\GdImage $img
     * @return array{0: int, 1: int, 2: int}
     */
    private function rgbAt($img, int $x, int $y): array
    {
        $c = imagecolorat($img, $x, $y);

        return [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF];
    }

    /**
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private function isRgbWhite(array $rgb): bool
    {
        return $rgb[0] === 255 && $rgb[1] === 255 && $rgb[2] === 255;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function randomRgbForLuminance(int $lumMin, int $lumMax): array
    {
        $lumMin = max(0, min(255, $lumMin));
        $lumMax = max(0, min(255, $lumMax));
        if ($lumMax < $lumMin) {
            [$lumMin, $lumMax] = [$lumMax, $lumMin];
        }
        for ($t = 0; $t < self::LUMINANCE_TRIES; $t++) {
            $r = mt_rand(0, 255);
            $g = mt_rand(0, 255);
            $b = mt_rand(0, 255);
            $lum = $this->luminance($r, $g, $b);
            if ($lum >= $lumMin && $lum <= $lumMax) {
                return [$r, $g, $b];
            }
        }
        $gray = (int) round(($lumMin + $lumMax) / 2);
        $gray = max($lumMin, min($lumMax, $gray));

        return [$gray, $gray, $gray];
    }

    private function luminance(int $r, int $g, int $b): int
    {
        return (int) round(0.299 * $r + 0.587 * $g + 0.114 * $b);
    }
}
