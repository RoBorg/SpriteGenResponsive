<?php

/**
 * Create a minimal transparent PNG
 */
class TransparentPngCreator
{
    /**
     * Create a data URI for a transparent PNG
     *
     * @param int the image width
     * @param int the image height
     *
     * @return string
     */
    public function getDataUri($width, $height)
    {
        return 'data:image/png;base64,' . base64_encode($this->getFile($width, $height));
    }

    /**
     * Create a transparent PNG
     *
     * @param int the image width
     * @param int the image height
     *
     * @return string
     */
    public function getFile($width, $height)
    {
        // File Header
        $data = pack('H*', '89504E470D0A1A0A');

        // Header (IHDR) chunk
        // Width, Height, Bit depth, Color type, Compression type, Filter type, Interlace type
        $chunkData = pack('NNCCCCC', $width, $height, 1, 3, 0, 0, 0);
        $data .= $this->createPngChunk('IHDR', $chunkData);

        // Palette chunk and Transparency (tRNA) chunk (cheat and use a pre-built one)
        $data .= pack('H*', '00000003504c5445ffffffa7c41bc80000000174524e530040e6d866');

        // Data (IDAT) chunk
        // Scanline = 1 bit per pixel, padded to a byte, plus 1 byte for the filter (all bits and bytes are 0)
        $chunkData = str_repeat(pack('C', 0), (1 + floor(($width + 7) / 8)) * $height);
        $data .= $this->createPngChunk('IDAT', gzcompress($chunkData, 9));

        // End (IEND) chunk
        $data .= $this->createPngChunk('IEND', '');

        return $data;
    }

    /**
     * Create a PNG chunk
     *
     * @param string the chunk name
     * @param string the chunk data
     *
     * @return string
     */
    protected function createPngChunk($name, $data)
    {
        return pack('N', strlen($data))
            . $name
            . $data
            . pack('H*', hash('crc32b', $name . $data));
    }
}
