<?php

class ResponsiveSprite
{
    /**
     * Align images to n-pixel boundaries to reduce artefacts
     * This should be 8 or 16
     */
    const JPEG_BOUNDARY = 16;

    protected $pngCreator;
    protected $image;
    protected $pngs = [];
    protected $inputFiles = [];
    protected $outputType = 'png';
    protected $jpegQuality = 75;
    protected $cssPrefix = '';
    protected $padding = 0;
    protected $reduceArtefacts = 0;
    protected $width = 0;
    protected $height = 0;

    public function __construct()
    {
        $this->pngCreator = new TransparentPngCreator();
    }

    public function run($images, $options = [])
    {
        if (empty($images)) {
            throw new Exception('No images supplied');
        }

        if (!empty($options['output_type'])) {
            $this->outputType = $options['output_type'];
        }

        if (isset($options['jpeg_quality'])) {
            $this->jpegQuality = (int)$options['jpeg_quality'];
        }

        if (!empty($options['css_prefix'])) {
            $this->cssPrefix = $options['css_prefix'];
        }

        if (!empty($options['padding'])) {
            $this->padding = (int)$options['padding'];
        }

        if (!empty($options['jpeg_reduce_artefacts'])) {
            $this->reduceArtefacts = (bool)$options['jpeg_reduce_artefacts'];
        }

        foreach ($images as $image) {
            $this->addImage($image);
        }

        // Sort the images by width and name
        usort($this->inputFiles, [$this, 'sort']);

        $this->pack();
        $this->createImage();
    }

    protected function addImage($image)
    {
        $file = $image['tmp_name'];

        $info = getimagesize($file);
        $pathInfo = pathinfo($file);
        $types = [
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_JPEG => 'jpeg',
        ];

        if (empty($types[$info[2]])) {
            throw new Exception('Invalid image file - ' . $image['name']);
        }

        $this->inputFiles[] = [
            'name' => $image['name'],
            'path' => $file,
            'pathInfo' => $pathInfo,
            'width' => $info[0],
            'height' => $info[1],
            'jpegWidth' => $this->toJpegBoundary($info[0]),
            'jpegHeight' => $this->toJpegBoundary($info[1]),
            'type' => $types[$info[2]],
            'x' => 0,
            'y' => 0,
            'css' => '',
        ];
    }

    /**
     * Align images to self::JPEG_BOUNDARY pixels if reduceArtefacts is true.
     * This alignment will help stop different component images bleeding into each other,
     * but will increase the size of the sprite
     *
     * @param int the pixel offset
     *
     * @return int the aligned pixel offset
     */
    protected function toJpegBoundary($num)
    {
        if (!$this->reduceArtefacts || !($num % self::JPEG_BOUNDARY)) {
            return $num;
        }

        return $num + self::JPEG_BOUNDARY - ($num % self::JPEG_BOUNDARY);
    }

    /**
     * Helper function to sort images by width and name
     *
     * @param mixed image one
     * @param mixed image two
     *
     * @return int sort order
     */
    protected function sort($a, $b)
    {
        if ($a['jpegWidth'] > $b['jpegWidth']) {
            return -1;
        }

        if ($a['jpegWidth'] < $b['jpegWidth']) {
            return 1;
        }

        return strcmp($a['name'], $b['name']);
    }

    /**
     * Calculate the size of the output image
     */
    protected function calculateSize()
    {
        $this->width = $this->inputFiles[0]['jpegWidth'];
        $this->height = 0;

        foreach ($this->inputFiles as $i => $image) {
            $this->height += $image['jpegHeight'];

            if ($i) {
                $this->height += $this->padding;
            }
        }
    }

    /**
     * Calculate the position of each image
     */
    protected function pack()
    {
        $x = 0;
        $y = 0;

        $this->calculateSize();

        foreach ($this->inputFiles as $i => $image) {
            $image['x'] = $x;
            $image['y'] = $y;

            $cssSize = (100 * $this->width) / $image['width'];
            $cssPosition = (100 * $y) / ($this->height - $image['height']);

            $image['class'] = $this->filenameToSelector($image['name']);
            $image['selector'] = $this->cssPrefix . $image['class'];
            $image['css'] = '.' . $image['selector'] . ' { background-position: 0 ' . round($cssPosition, 6) . '%; background-size: ' . round($cssSize, 6) . "%; }\n";

            $this->inputFiles[$i] = $image;

            $y += $image['jpegHeight'] + $this->padding;
        }
    }

    /**
     * Place the images onto the sprite image
     */
    protected function createImage()
    {
        $img = imagecreatetruecolor($this->width, $this->height);

        // Fill with transparency
        imagealphablending($img, false);
        $c = imagecolorallocatealpha($img, 255, 255, 255, 127);
        imagefilledrectangle($img, 0, 0, $this->width - 1, $this->height - 1, $c);

        // Copy in the images
        foreach ($this->inputFiles as $image) {
            $f = 'imagecreatefrom' . $image['type'];
            $src = $f($image['path']);

            imagecopy($img, $src, $image['x'], $image['y'], 0, 0, $image['width'], $image['height']);

            if ($this->reduceArtefacts) {
                for ($x = $image['width']; $x < $image['jpegWidth']; $x++) {
                    imagecopy($img, $src, $image['x'] + $x, $image['y'], $image['width'] - 1, 0, 1, $image['height']);
                }

                for ($y = $image['height']; $y < $image['jpegHeight']; $y++) {
                    imagecopy($img, $img, $image['x'], $image['y'] + $y, $image['x'], $image['y'] + $image['height'] - 1, $image['jpegWidth'], 1);
                }
            }
        }

        imagesavealpha($img, true);
        $this->image = $img;
    }

    /**
     * Get an output image from the GD resource
     *
     * @return string the image file data
     */
    public function getImage()
    {
        $f = 'image' . $this->outputType;

        $options = [];

        if ($this->outputType == 'jpeg') {
            $options['quality'] = $this->jpegQuality;
        }

        ob_start();

        array_unshift($options, null);
        array_unshift($options, $this->image);
        call_user_func_array($f, $options);

        $image = ob_get_clean();

        return $image;
    }

    /**
     * Get the CSS to display the sprites
     *
     * @param string the URL of the sprite image
     *
     * @return string
     */
    public function getCss($url = '')
    {
        $css = '';
        $baseRule = '';

        if ($url == '') {
            $url = $this->outputType . '.' . ($this->outputType === 'jpeg' ? 'jpg' : $this->outputType);
        }

        foreach ($this->inputFiles as $i => $image) {
            $baseRule .= ($baseRule ? ', ' : '') . ($i % 5 ? '' : "\n") . '.' . $image['selector'];
            $css .= $image['css'];
        }

        $css = "/* Generated by http://responsive-css.spritegen.com Responsive CSS Sprite Generator */\n"
            . $baseRule . "\n{ max-width: 100%; background-size: 100%; background-image: url('" . $url . "'); }\n\n" . $css;

        return $css;
    }

    /**
     * Get the HTML to display the sprites
     *
     * @return string
     */
    public function getHtml()
    {
        $html = '';

        foreach ($this->inputFiles as $image) {
            $size = $image['width'] . 'x' . $image['height'];

            if (empty($this->pngs[$size])) {
                $this->pngs['size'] = $this->pngCreator->getDataUri($image['width'], $image['height']);
            }

            $html .= '<img class="' . $image['selector'] . '" alt="" src="' . $this->pngs['size'] . '">' . "\n";
        }

        return $html;
    }

    /**
     * Convert a filename to a CSS-friendly selector fragment
     *
     * @param string the filename
     *
     * @return string the selector fragment
     */
    protected function filenameToSelector($str)
    {
        $str = mb_strtolower($str);
        $str = preg_replace('/\.[^.]*$/u', '', $str);
        $str = preg_replace('/\'/u', '', $str);
        $str = preg_replace('/[^a-z0-9]/u', '-', $str);
        $str = preg_replace('/-+/u', '-', $str);
        $str = preg_replace('/(^-)|(-$)/u', '', $str);

        if (($this->cssPrefix === '') && preg_match('/^[0-9]/', $str)) {
            $str = 'img-' . $str;
        }

        return $str;
    }

    /**
     * Get the image as a data URI
     */
    public function getDataUri()
    {
        return 'data:image/' . $this->outputType . ';base64,' . base64_encode($this->getImage());
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if ($this->image) {
            imagedestroy($this->image);
        }
    }
}
