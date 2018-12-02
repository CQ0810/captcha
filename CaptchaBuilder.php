<?php

namespace Captcha;

use Captcha\Interfaces\CaptchaBuilderInterface;
use Captcha\Interfaces\PhraseBuilderInterface;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\FilesystemCache;
use \Exception;

/**
 * Builds a new captcha image
 * Uses the fingerprint parameter, if one is passed, to generate the same image
 *
 * @author macro chen <chen_macro@a63.com>
 */
class CaptchaBuilder implements CaptchaBuilderInterface
{
    /**
     * @var array
     */
    protected $fingerprint = array();

    /**
     * @var bool
     */
    protected $useFingerprint = false;

    /**
     * @var array
     */
    protected $textColor = null;

    /**
     * @var array
     */
    protected $backgroundColor = null;

    /**
     * @var array
     */
    protected $backgroundImages = array();

    /**
     * @var resource
     */
    protected $contents = null;

    /**
     * @var string
     */
    protected $phrase = null;

    /**
     * @var PhraseBuilderInterface
     */
    protected $builder;

    /**
     * @var bool
     */
    protected $distortion = true;

    /**
     * The maximum number of lines to draw in front of
     * the image. null - use default algorithm
     */
    protected $maxFrontLines = null;

    /**
     * The maximum number of lines to draw behind
     * the image. null - use default algorithm
     */
    protected $maxBehindLines = null;

    /**
     * The maximum angle of char
     */
    protected $maxAngle = 8;

    /**
     * The maximum offset of char
     */
    protected $maxOffset = 5;

    /**
     * Is the interpolation enabled ?
     *
     * @var bool
     */
    protected $interpolation = true;

    /**
     * Ignore all effects
     *
     * @var bool
     */
    protected $ignoreAllEffects = false;

    /**
     * Allowed image types for the background images
     *
     * @var array
     */
    protected $allowedBackgroundImageTypes = ['image/png', 'image/jpeg', 'image/gif'];

    /**
     * Temporary dir, for OCR check
     */
    protected $tempDir = APP_PATH . '/log/temp/';

    /**
     * 缓存
     *
     * @var Cache
     */
    protected $cache = null;

    /**
     * @var int
     */
    private $background = 0;

    /**
     * CaptchaBuilder constructor.
     *
     * @param string                      $phrase
     * @param Cache                       $cache
     * @param PhraseBuilderInterface|null $builder
     *
     * @throws Exception
     */
    public function __construct($phrase = null, Cache $cache = null, PhraseBuilderInterface $builder = null)
    {
        $builder === null ? $this->builder = new PhraseBuilder : $this->builder = $builder;
        $cache === null ? $this->cache = new FilesystemCache(sys_get_temp_dir()) : $this->cache = $cache;
        if ($phrase === null) {
            $phrase = $this->builder->build();
        }
        $this->phrase = $phrase;
    }

    /**
     * The image contents
     */
    public function getContents()
    {
        return $this->contents;
    }

    /**
     * Enable/Disables the interpolation
     *
     * @param $interpolate bool  True to enable, false to disable
     *
     * @return CaptchaBuilder
     */
    public function setInterpolation($interpolate = true)
    {
        $this->interpolation = $interpolate;
        return $this;
    }

    /**
     * Setting the phrase
     *
     * @param $phrase
     */
    public function setPhrase($phrase)
    {
        $this->phrase = (string)$phrase;
    }

    /**
     * Enables/disable distortion
     *
     * @param $distortion
     *
     * @return $this
     */
    public function setDistortion($distortion)
    {
        $this->distortion = (bool)$distortion;
        return $this;
    }

    /**
     * @param $maxBehindLines
     *
     * @return $this
     */
    public function setMaxBehindLines($maxBehindLines)
    {
        $this->maxBehindLines = $maxBehindLines;
        return $this;
    }

    /**
     * @param $maxFrontLines
     *
     * @return $this
     */
    public function setMaxFrontLines($maxFrontLines)
    {
        $this->maxFrontLines = $maxFrontLines;
        return $this;
    }

    /**
     * @param $maxAngle
     *
     * @return $this
     */
    public function setMaxAngle($maxAngle)
    {
        $this->maxAngle = $maxAngle;
        return $this;
    }

    /**
     * @param $maxOffset
     *
     * @return $this
     */
    public function setMaxOffset($maxOffset)
    {
        $this->maxOffset = $maxOffset;
        return $this;
    }

    /**
     * Gets the captcha phrase
     */
    public function getPhrase()
    {
        return $this->phrase;
    }

    /**
     * Returns true if the given phrase is good
     *
     * @param $phrase
     *
     * @return bool
     */
    public function testPhrase($phrase)
    {
        return ($this->builder->niceize($phrase) === $this->builder->niceize($this->getPhrase()));
    }

    /**
     * Instantiation
     *
     * @param array $params
     *
     * @throws Exception
     * @return CaptchaBuilder
     */
    public static function create(array $params = [])
    {
        $phrase = isset($params['phrase']) ? $params['phrase'] : null;
        $cache = isset($params['cache']) ? $params['cache'] : null;
        return new self($phrase, $cache);
    }

    /**
     * Sets the text color to use
     *
     * @param $r
     * @param $g
     * @param $b
     *
     * @return $this
     */
    public function setTextColor($r, $g, $b)
    {
        $this->textColor = array($r, $g, $b);
        return $this;
    }

    /**
     * Sets the background color to use
     *
     * @param $r
     * @param $g
     * @param $b
     *
     * @return $this
     */
    public function setBackgroundColor($r, $g, $b)
    {
        $this->backgroundColor = array($r, $g, $b);
        return $this;
    }

    /**
     * Sets the ignoreAllEffects value
     *
     * @param bool $ignoreAllEffects
     *
     * @return CaptchaBuilder
     */
    public function setIgnoreAllEffects($ignoreAllEffects)
    {
        $this->ignoreAllEffects = $ignoreAllEffects;
        return $this;
    }

    /**
     * Sets the list of background images to use (one image is randomly selected)
     *
     * @param array $backgroundImages
     *
     * @return $this
     */
    public function setBackgroundImages(array $backgroundImages)
    {
        $this->backgroundImages = $backgroundImages;
        return $this;
    }

    /**
     * Draw lines over the image
     *
     * @param       $image
     * @param       $width
     * @param       $height
     * @param mixed $col
     */
    protected function drawLine($image, $width, $height, $col = null)
    {
        if ($col === null) {
            $col = imagecolorallocate($image, $this->rand(100, 255), $this->rand(100, 255), $this->rand(100, 255));
        }
        if ($this->rand(0, 1)) {
            $Xa = $this->rand(0, $width / 2);
            $Ya = $this->rand(0, $height);
            $Xb = $this->rand($width / 2, $width);
            $Yb = $this->rand(0, $height);
        } else {
            $Xa = $this->rand(0, $width);
            $Ya = $this->rand(0, $height / 2);
            $Xb = $this->rand(0, $width);
            $Yb = $this->rand($height / 2, $height);
        }
        imagesetthickness($image, $this->rand(1, 3));
        imageline($image, $Xa, $Ya, $Xb, $Yb, $col);
    }

    /**
     * Apply some post effects
     */
    protected function postEffect($image)
    {
        if (!function_exists('imagefilter')) {
            return;
        }
        if ($this->backgroundColor !== null || $this->textColor != null) {
            return;
        }
        if ($this->rand(0, 1) === 0) {
            imagefilter($image, IMG_FILTER_NEGATE);
        }
        if ($this->rand(0, 10) === 0) {
            imagefilter($image, IMG_FILTER_EDGEDETECT);
        }
        imagefilter($image, IMG_FILTER_CONTRAST, $this->rand(-50, 10));
        if ($this->rand(0, 5) === 0) {
            imagefilter($image, IMG_FILTER_COLORIZE, $this->rand(-80, 50), $this->rand(-80, 50), $this->rand(-80, 50));
        }
    }

    /**
     * Writes the phrase on the image
     *
     * @param $image
     * @param $phrase
     * @param $font
     * @param $width
     * @param $height
     *
     * @return int
     */
    protected function writePhrase($image, $phrase, $font, $width, $height)
    {
        $length = strlen($phrase);
        if ($length === 0) {
            return imagecolorallocate($image, 0, 0, 0);
        }
        $size = $width / $length - $this->rand(0, 3) - 1;
        $box = imagettfbbox($size, 0, $font, $phrase);
        $textWidth = $box[2] - $box[0];
        $textHeight = $box[1] - $box[7];
        $x = ($width - $textWidth) / 2;
        $y = ($height - $textHeight) / 2 + $size;
        if (!count($this->textColor)) {
            $textColor = array($this->rand(0, 150), $this->rand(0, 150), $this->rand(0, 150));
        } else {
            $textColor = $this->textColor;
        }
        $col = imagecolorallocate($image, $textColor[0], $textColor[1], $textColor[2]);
        for ($i = 0; $i < $length; $i++) {
            $box = imagettfbbox($size, 0, $font, $phrase[$i]);
            $w = $box[2] - $box[0];
            $angle = $this->rand(-$this->maxAngle, $this->maxAngle);
            $offset = $this->rand(-$this->maxOffset, $this->maxOffset);
            imagettftext($image, $size, $angle, $x, $y + $offset, $col, $font, $phrase[$i]);
            $x += $w;
        }
        return $col;
    }

    /**
     * Try to read the code against an OCR
     *
     * @return bool|int
     */
    public function isOCRReadable()
    {
        $ret = is_dir($this->tempDir) ? 1 : mkdir($this->tempDir, 0755, true);
        if (!$ret) {
            return 0;
        }
        $jpg = $this->tempDir . uniqid('captcha', true) . '.jpg';
        $pgm = $this->tempDir . uniqid('captcha', true) . '.pgm';
        $this->save($jpg);
        shell_exec("convert $jpg $pgm");
        $value = trim(strtolower(shell_exec("ocrad $pgm")));
        @unlink($jpg);
        @unlink($pgm);
        return $this->testPhrase($value);
    }

    /**
     * Builds while the code is readable against an OCR
     *
     * @param int  $width
     * @param int  $height
     * @param null $font
     * @param null $fingerprint
     */
    public function buildAgainstOCR($width = 150, $height = 40, $font = null, $fingerprint = null)
    {
        do {
            $this->build($width, $height, $font, $fingerprint);
        } while ($this->isOCRReadable());
    }

    /**
     * Generate the image
     *
     * @param int    $width
     * @param int    $height
     * @param string $font
     * @param null   $fingerprint
     *
     * @return $this
     */
    public function build($width = 150, $height = 40, $font = '', $fingerprint = null)
    {
        if (null !== $fingerprint) {
            $this->fingerprint = $fingerprint;
            $this->useFingerprint = true;
        } else {
            $this->fingerprint = array();
            $this->useFingerprint = false;
        }
        if ($font === '') {
            $font = __DIR__ . '/Font/captcha' . $this->rand(0, 5) . '.ttf';
        }
        if (empty($this->backgroundImages)) {
            $image = imagecreatetruecolor($width, $height);
            if ($this->backgroundColor === null) {
                $bg = imagecolorallocate($image, $this->rand(200, 255), $this->rand(200, 255), $this->rand(200, 255));
            } else {
                $color = $this->backgroundColor;
                $bg = imagecolorallocate($image, $color[0], $color[1], $color[2]);
            }
            $this->background = $bg;
            imagefill($image, 0, 0, $bg);
        } else {
            $randomBackgroundImage = $this->backgroundImages[rand(0, count($this->backgroundImages) - 1)];
            $imageType = $this->validateBackgroundImage($randomBackgroundImage);
            $image = $this->createBackgroundImageFromType($randomBackgroundImage, $imageType);
        }
        if (!$this->ignoreAllEffects) {
            $square = $width * $height;
            $effects = $this->rand($square / 3000, $square / 2000);
            if ($this->maxBehindLines !== null && $this->maxBehindLines > 0) {
                $effects = min($this->maxBehindLines, $effects);
            }
            if ($this->maxBehindLines !== 0) {
                for ($e = 0; $e < $effects; $e++) {
                    $this->drawLine($image, $width, $height);
                }
            }
        }
        $color = $this->writePhrase($image, $this->phrase, $font, $width, $height);
        if (!$this->ignoreAllEffects) {
            $square = $width * $height;
            $effects = $this->rand($square / 3000, $square / 2000);
            if ($this->maxFrontLines !== null && $this->maxFrontLines > 0) {
                $effects = min($this->maxFrontLines, $effects);
            }
            if ($this->maxFrontLines !== 0) {
                for ($e = 0; $e < $effects; $e++) {
                    $this->drawLine($image, $width, $height, $color);
                }
            }
        }
        if ($this->distortion && !$this->ignoreAllEffects) {
            $image = $this->distort($image, $width, $height, $bg);
        }
        if (!$this->ignoreAllEffects) {
            $this->postEffect($image);
        }
        $this->contents = $image;
        $lifeTime = function_exists('app') ? app()->config('app.captcha_lifetime', 60) : 60;
        $this->cache->save(md5($this->phrase), $this->phrase, $lifeTime);
        return $this;
    }

    /**
     * Distorts the image
     */
    public function distort($image, $width, $height, $bg)
    {
        $contents = imagecreatetruecolor($width, $height);
        $X = $this->rand(0, $width);
        $Y = $this->rand(0, $height);
        $phase = $this->rand(0, 10);
        $scale = 1.1 + $this->rand(0, 10000) / 30000;
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $Vx = $x - $X;
                $Vy = $y - $Y;
                $Vn = sqrt($Vx * $Vx + $Vy * $Vy);
                if ($Vn !== 0.0) {
                    $Vn2 = $Vn + 4 * sin($Vn / 30);
                    $nX = $X + ($Vx * $Vn2 / $Vn);
                    $nY = $Y + ($Vy * $Vn2 / $Vn);
                } else {
                    $nX = $X;
                    $nY = $Y;
                }
                $nY += $scale * sin($phase + $nX * 0.2);
                if ($this->interpolation) {
                    $p = $this->interpolate(
                        $nX - floor($nX),
                        $nY - floor($nY),
                        $this->getCol($image, floor($nX), floor($nY), $bg),
                        $this->getCol($image, ceil($nX), floor($nY), $bg),
                        $this->getCol($image, floor($nX), ceil($nY), $bg),
                        $this->getCol($image, ceil($nX), ceil($nY), $bg)
                    );
                } else {
                    $p = $this->getCol($image, round($nX), round($nY), $bg);
                }
                if ((int)$p === 0) {
                    $p = $bg;
                }
                imagesetpixel($contents, $x, $y, $p);
            }
        }
        return $contents;
    }

    /**
     * Saves the Captcha to a jpeg file
     *
     * @param     $filename
     * @param int $quality
     */
    public function save($filename, $quality = 90)
    {
        imagejpeg($this->contents, $filename, $quality);
    }

    /**
     * Gets the image GD
     */
    public function getGd()
    {
        return $this->contents;
    }

    /**
     * Gets the image contents
     *
     * @param int $quality
     *
     * @return string
     */
    public function get($quality = 90)
    {
        ob_start();
        $this->output($quality);
        return ob_get_clean();
    }

    /**
     * Gets the HTML inline base64
     *
     * @param int $quality
     *
     * @return string
     */
    public function inline($quality = 90)
    {
        return 'data:image/jpeg;base64,' . base64_encode($this->get($quality));
    }

    /**
     * Outputs the image
     *
     * @param int $quality
     */
    public function output($quality = 90)
    {
        imagejpeg($this->contents, null, $quality);
    }

    /**
     * @return array
     */
    public function getFingerprint()
    {
        return $this->fingerprint;
    }

    /**
     * Returns a random number or the next number in the
     * fingerprint
     *
     * @param $min
     * @param $max
     *
     * @return int|mixed
     */
    protected function rand($min, $max)
    {
        if (!is_array($this->fingerprint)) {
            $this->fingerprint = array();
        }
        if ($this->useFingerprint) {
            $value = current($this->fingerprint);
            next($this->fingerprint);
        } else {
            $value = mt_rand($min, $max);
            $this->fingerprint[] = $value;
        }
        return $value;
    }

    /**
     * @param $x
     * @param $y
     * @param $nw
     * @param $ne
     * @param $sw
     * @param $se
     *
     * @return int
     */
    protected function interpolate($x, $y, $nw, $ne, $sw, $se)
    {
        list($r0, $g0, $b0) = $this->getRGB($nw);
        list($r1, $g1, $b1) = $this->getRGB($ne);
        list($r2, $g2, $b2) = $this->getRGB($sw);
        list($r3, $g3, $b3) = $this->getRGB($se);
        $cx = 1.0 - $x;
        $cy = 1.0 - $y;
        $m0 = $cx * $r0 + $x * $r1;
        $m1 = $cx * $r2 + $x * $r3;
        $r = (int)($cy * $m0 + $y * $m1);
        $m0 = $cx * $g0 + $x * $g1;
        $m1 = $cx * $g2 + $x * $g3;
        $g = (int)($cy * $m0 + $y * $m1);
        $m0 = $cx * $b0 + $x * $b1;
        $m1 = $cx * $b2 + $x * $b3;
        $b = (int)($cy * $m0 + $y * $m1);
        return ($r << 16) | ($g << 8) | $b;
    }

    /**
     * @param $image
     * @param $x
     * @param $y
     *
     * @return int
     */
    protected function getCol($image, $x, $y, $background)
    {
        $L = imagesx($image);
        $H = imagesy($image);
        if ($x < 0 || $x >= $L || $y < 0 || $y >= $H) {
            return $background;
        }
        return imagecolorat($image, $x, $y);
    }

    /**
     * @param $col
     *
     * @return array
     */
    protected function getRGB($col)
    {
        return array(
            (int)($col >> 16) & 0xff,
            (int)($col >> 8) & 0xff,
            (int)$col & 0xff,
        );
    }

    /**
     * Validate the background image path. Return the image type if valid
     *
     * @param string $backgroundImage
     *
     * @return string
     * @throws Exception
     */
    protected function validateBackgroundImage($backgroundImage)
    {
        if (!file_exists($backgroundImage)) {
            $backgroundImageExploded = explode('/', $backgroundImage);
            $imageFileName = count($backgroundImageExploded) > 1 ? $backgroundImageExploded[count($backgroundImageExploded) - 1] : $backgroundImage;
            throw new \InvalidArgumentException('Invalid background image: ' . $imageFileName);
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $imageType = finfo_file($finfo, $backgroundImage);
        finfo_close($finfo);
        if (!in_array($imageType, $this->allowedBackgroundImageTypes, false)) {
            $str = implode(', ', $this->allowedBackgroundImageTypes);
            throw new \InvalidArgumentException('Invalid background image type! Allowed types are: ' . $str);
        }
        return $imageType;
    }

    /**
     * Create background image from type
     *
     * @param string $backgroundImage
     * @param string $imageType
     *
     * @return resource
     * @throws Exception
     */
    protected function createBackgroundImageFromType($backgroundImage, $imageType)
    {
        switch ($imageType) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($backgroundImage);
                break;
            case 'image/png':
                $image = imagecreatefrompng($backgroundImage);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($backgroundImage);
                break;
            default:
                throw new \InvalidArgumentException('Not supported file type for background image!');
                break;
        }
        return $image;
    }
}

