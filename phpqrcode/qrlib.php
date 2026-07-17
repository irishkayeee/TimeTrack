<?php
class QRcode {
    public static function png($text, $outfile = false, $level = 'L', $size = 4, $margin = 2) {
        $encoder = QRencode::factory($level, $size, $margin);
        return $encoder->encodePNG($text, $outfile);
    }
}

class QRencode {
    private static $capacity = [
        'L' => [1 => 17, 2 => 32, 3 => 53, 4 => 78],
        'M' => [1 => 14, 2 => 26, 3 => 42, 4 => 62],
        'Q' => [1 => 11, 2 => 20, 3 => 32, 4 => 46],
        'H' => [1 => 7, 2 => 14, 3 => 24, 4 => 34]
    ];

    private static $dataCodewords = [
        1 => ['L' => 19, 'M' => 16, 'Q' => 13, 'H' => 9],
        2 => ['L' => 34, 'M' => 28, 'Q' => 22, 'H' => 16],
        3 => ['L' => 55, 'M' => 44, 'Q' => 34, 'H' => 26],
        4 => ['L' => 80, 'M' => 64, 'Q' => 48, 'H' => 36]
    ];

    private static $ecCodewords = [
        1 => ['L' => 7, 'M' => 10, 'Q' => 13, 'H' => 17],
        2 => ['L' => 10, 'M' => 16, 'Q' => 22, 'H' => 28],
        3 => ['L' => 15, 'M' => 26, 'Q' => 36, 'H' => 44],
        4 => ['L' => 20, 'M' => 36, 'Q' => 52, 'H' => 64]
    ];

    private static $blockInfo = [
        1 => ['L' => [1, 19], 'M' => [1, 16], 'Q' => [1, 13], 'H' => [1, 9]],
        2 => ['L' => [1, 34], 'M' => [1, 28], 'Q' => [1, 22], 'H' => [1, 16]],
        3 => ['L' => [1, 55], 'M' => [1, 44], 'Q' => [2, 17], 'H' => [2, 13]],
        4 => ['L' => [1, 80], 'M' => [2, 32], 'Q' => [2, 24], 'H' => [4, 9]]
    ];

    private static $alignmentPatterns = [
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26]
    ];

    private static $formatBits = ['L' => 0b01, 'M' => 0b00, 'Q' => 0b11, 'H' => 0b10];
    private static $gfExp = null;
    private static $gfLog = null;

    private $level;
    private $size;
    private $margin;

    public function __construct($level = 'L', $size = 4, $margin = 2) {
        $level = strtoupper($level);
        if (!isset(self::$capacity[$level])) {
            $level = 'L';
        }
        $this->level = $level;
        $this->size = max(1, min(20, (int)$size));
        $this->margin = max(0, (int)$margin);
    }

    public static function factory($level = 'L', $size = 4, $margin = 2) {
        return new self($level, $size, $margin);
    }

    public function encodePNG($text, $outfile = false) {
        $matrix = $this->encode($text);
        return QRimage::png($matrix, $outfile, $this->size, $this->margin);
    }

    public function encode($text) {
        $data = array_values(unpack('C*', mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8')));
        $version = $this->selectVersion(count($data));
        $dataCodewords = $this->createDataCodewords($data, $version);
        $finalCodewords = $this->createFinalCodewords($dataCodewords, $version);
        return $this->buildMatrix($finalCodewords, $version);
    }

    private function selectVersion($length) {
        foreach (self::$capacity[$this->level] as $version => $limit) {
            if ($length <= $limit) {
                return $version;
            }
        }
        throw new Exception('Text too long for QR code generation.');
    }

    private function createDataCodewords($data, $version) {
        $dataCapacity = self::$dataCodewords[$version][$this->level];
        $maxBits = $dataCapacity * 8;
        $bits = [];
        $this->appendBits(0x4, 4, $bits); // byte mode
        $this->appendBits(count($data), 8, $bits);
        foreach ($data as $byte) {
            $this->appendBits($byte, 8, $bits);
        }
        $terminator = min(4, max(0, $maxBits - count($bits)));
        for ($i = 0; $i < $terminator; $i++) {
            $bits[] = 0;
        }
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }
        $padBytes = [0xEC, 0x11];
        $padIndex = 0;
        while ((count($bits) / 8) < $dataCapacity) {
            $this->appendBits($padBytes[$padIndex++ % 2], 8, $bits);
        }
        return $this->bitsToBytes($bits);
    }

    private function createFinalCodewords($dataCodewords, $version) {
        list($blocks, $blockSize) = self::$blockInfo[$version][$this->level];
        $ecCount = self::$ecCodewords[$version][$this->level];
        $blocksData = [];
        $offset = 0;
        for ($b = 0; $b < $blocks; $b++) {
            $blockData = array_slice($dataCodewords, $offset, $blockSize);
            $offset += $blockSize;
            $blocksData[] = [
                'data' => $blockData,
                'ecc' => $this->calculateEcc($blockData, $ecCount)
            ];
        }
        $result = [];
        for ($i = 0; $i < $blockSize; $i++) {
            foreach ($blocksData as $block) {
                if (isset($block['data'][$i])) {
                    $result[] = $block['data'][$i];
                }
            }
        }
        for ($i = 0; $i < $ecCount; $i++) {
            foreach ($blocksData as $block) {
                $result[] = $block['ecc'][$i];
            }
        }
        return $result;
    }

    private function buildMatrix($codewords, $version) {
        $size = $version * 4 + 17;
        $matrix = array_fill(0, $size, array_fill(0, $size, -1));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));
        $this->setupFinderPatterns($matrix, $reserved);
        $this->setupTimingPatterns($matrix, $reserved);
        if ($version >= 2) {
            $this->setupAlignmentPatterns($matrix, $reserved, self::$alignmentPatterns[$version]);
        }
        $this->setupDarkModule($matrix, $reserved, $version);
        $this->placeData($matrix, $reserved, $this->codewordsToBits($codewords));
        $this->applyMask($matrix, $reserved);
        $this->placeFormatInformation($matrix, $reserved, $version);
        return $matrix;
    }

    private function setupFinderPatterns(&$matrix, &$reserved) {
        $this->placeFinderPattern($matrix, $reserved, 0, 0);
        $this->placeFinderPattern($matrix, $reserved, count($matrix) - 7, 0);
        $this->placeFinderPattern($matrix, $reserved, 0, count($matrix) - 7);
    }

    private function placeFinderPattern(&$matrix, &$reserved, $row, $col) {
        $size = count($matrix);
        for ($r = 0; $r < 7; $r++) {
            for ($c = 0; $c < 7; $c++) {
                $isBorder = $r === 0 || $r === 6 || $c === 0 || $c === 6;
                $isCenter = $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4;
                $value = $isBorder || $isCenter ? 1 : 0;
                if (isset($matrix[$row + $r][$col + $c])) {
                    $matrix[$row + $r][$col + $c] = $value;
                }
                if (isset($reserved[$row + $r][$col + $c])) {
                    $reserved[$row + $r][$col + $c] = true;
                }
            }
        }
        $topRow = $row - 1 >= 0 ? $row - 1 : $row;
        $bottomRow = $row + 7 < $size ? $row + 7 : $row + 6;
        $leftCol = $col - 1 >= 0 ? $col - 1 : $col;
        $rightCol = $col + 7 < $size ? $col + 7 : $col + 6;
        for ($i = 0; $i < 8; $i++) {
            $targetCol = $col + $i;
            if ($targetCol >= 0 && $targetCol < $size) {
                $matrix[$topRow][$targetCol] = 0;
                $matrix[$bottomRow][$targetCol] = 0;
            }
            $targetRow = $row + $i;
            if ($targetRow >= 0 && $targetRow < $size) {
                $matrix[$targetRow][$leftCol] = 0;
                $matrix[$targetRow][$rightCol] = 0;
            }
            if ($row === 0 && $col === 0 && isset($matrix[7][8], $matrix[8][7], $reserved[7][8], $reserved[8][7])) {
                $matrix[7][8] = 1;
                $reserved[7][8] = true;
                $matrix[8][7] = 1;
                $reserved[8][7] = true;
            }
        }
    }

    private function setupTimingPatterns(&$matrix, &$reserved) {
        $size = count($matrix);
        for ($i = 8; $i < $size - 8; $i++) {
            if ($reserved[6][$i] === false) {
                $matrix[6][$i] = $i % 2 === 0 ? 1 : 0;
                $reserved[6][$i] = true;
            }
            if ($reserved[$i][6] === false) {
                $matrix[$i][6] = $i % 2 === 0 ? 1 : 0;
                $reserved[$i][6] = true;
            }
        }
    }

    private function setupAlignmentPatterns(&$matrix, &$reserved, $positions) {
        for ($r = 0; $r < count($positions); $r++) {
            for ($c = 0; $c < count($positions); $c++) {
                $row = $positions[$r];
                $col = $positions[$c];
                if ($row === 6 && $col === 6) {
                    continue;
                }
                $this->placeAlignmentPattern($matrix, $reserved, $row - 2, $col - 2);
            }
        }
    }

    private function placeAlignmentPattern(&$matrix, &$reserved, $row, $col) {
        for ($r = 0; $r < 5; $r++) {
            for ($c = 0; $c < 5; $c++) {
                $value = ($r === 0 || $r === 4 || $c === 0 || $c === 4 || ($r === 2 && $c === 2)) ? 1 : 0;
                $matrix[$row + $r][$col + $c] = $value;
                $reserved[$row + $r][$col + $c] = true;
            }
        }
    }

    private function setupDarkModule(&$matrix, &$reserved, $version) {
        $row = 4 * $version + 9;
        $col = 8;
        $matrix[$row][$col] = 1;
        $reserved[$row][$col] = true;
    }

    private function placeData(&$matrix, &$reserved, $bits) {
        $size = count($matrix);
        $row = $size - 1;
        $col = $size - 1;
        $direction = -1;
        $bitIndex = 0;
        while ($col > 0) {
            if ($col === 6) {
                $col--;
            }
            for ($i = 0; $i < $size; $i++) {
                $r = $direction > 0 ? $i : $size - 1 - $i;
                for ($c = 0; $c < 2; $c++) {
                    $x = $col - $c;
                    if ($x < 0 || $x >= $size || !isset($reserved[$r][$x]) || $reserved[$r][$x]) {
                        continue;
                    }
                    $matrix[$r][$x] = isset($bits[$bitIndex]) ? $bits[$bitIndex] : 0;
                    $bitIndex++;
                }
            }
            $col -= 2;
            $direction = -$direction;
        }
    }

    private function applyMask(&$matrix, $reserved) {
        $size = count($matrix);
        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                if (!isset($reserved[$row][$col]) || $reserved[$row][$col] === false) {
                    if ((($row + $col) & 1) === 0) {
                        $matrix[$row][$col] ^= 1;
                    }
                }
            }
        }
    }

    private function placeFormatInformation(&$matrix, &$reserved, $version) {
        $format = $this->createFormatInfo(0);
        $size = count($matrix);

        for ($i = 0; $i < 15; $i++) {
            $bit = ($format >> $i) & 1;
            if ($i < 6) {
                $matrix[8][$i] = $bit;
                $reserved[8][$i] = true;
            } elseif ($i < 8) {
                $matrix[8][$i + 1] = $bit;
                $reserved[8][$i + 1] = true;
            } else {
                $target = count($matrix) - 15 + $i;
                if (isset($matrix[8][$target])) {
                    $matrix[8][$target] = $bit;
                }
                if (isset($reserved[8][$target])) {
                    $reserved[8][$target] = true;
                }
            }
        }

        for ($i = 0; $i < 15; $i++) {
            $bit = ($format >> $i) & 1;
            if ($i < 8) {
                $matrix[$size - 1 - $i][8] = $bit;
                $reserved[$size - 1 - $i][8] = true;
            } elseif ($i === 8) {
                $matrix[8][7] = $bit;
                $reserved[8][7] = true;
            } else {
                $matrix[14 - $i][8] = $bit;
                $reserved[14 - $i][8] = true;
            }
        }
    }

    private function createFormatInfo($mask) {
        $data = (self::$formatBits[$this->level] << 3) | ($mask & 0x07);
        $formatInfo = ($data << 10) ^ $this->calculateBCHCode($data);
        return $formatInfo ^ 0x5412;
    }

    private function calculateBCHCode($value) {
        $g = 0x537;
        $value <<= 10;
        while ($this->bitLength($value) - 1 >= 10) {
            $shift = $this->bitLength($value) - 1 - 10;
            $value ^= $g << $shift;
        }
        return $value;
    }

    private function bitLength($value) {
        $length = 0;
        while ($value > 0) {
            $value >>= 1;
            $length++;
        }
        return $length;
    }

    private function appendBits($value, $length, &$bits) {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    private function bitsToBytes($bits) {
        $bytes = [];
        $count = count($bits);
        for ($i = 0; $i < $count; $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | ($bits[$i + $j] ?? 0);
            }
            $bytes[] = $byte;
        }
        return $bytes;
    }

    private function codewordsToBits($codewords) {
        $bits = [];
        foreach ($codewords as $byte) {
            $this->appendBits($byte, 8, $bits);
        }
        return $bits;
    }

    private function calculateEcc($data, $ecCount) {
        $this->initGalois();
        $generator = $this->createGeneratorPolynomial($ecCount);
        $coeffs = array_fill(0, $ecCount, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($coeffs);
            $coeffs[] = 0;
            if ($factor !== 0) {
                for ($i = 0; $i < $ecCount; $i++) {
                    $coeffs[$i] ^= $this->gfMultiply($generator[$i + 1], $factor);
                }
            }
        }
        return $coeffs;
    }

    private function initGalois() {
        if (self::$gfExp !== null) {
            return;
        }
        self::$gfExp = array_fill(0, 512, 0);
        self::$gfLog = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$gfExp[$i] = $x;
            self::$gfLog[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11d;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$gfExp[$i] = self::$gfExp[$i - 255];
        }
    }

    private function gfMultiply($x, $y) {
        if ($x === 0 || $y === 0) {
            return 0;
        }
        return self::$gfExp[(self::$gfLog[$x] + self::$gfLog[$y]) % 255];
    }

    private function createGeneratorPolynomial($degree) {
        $poly = [1];
        for ($i = 0; $i < $degree; $i++) {
            $poly = $this->multiplyPolynomials($poly, [1, self::$gfExp[$i]]);
        }
        return $poly;
    }

    private function multiplyPolynomials($p, $q) {
        $result = array_fill(0, count($p) + count($q) - 1, 0);
        foreach ($p as $i => $pi) {
            foreach ($q as $j => $qj) {
                $result[$i + $j] ^= $this->gfMultiply($pi, $qj);
            }
        }
        return $result;
    }
}

class QRimage {
    public static function png($frame, $filename = false, $pixelPerPoint = 4, $outerFrame = 2) {
        $size = count($frame);
        $imageSize = ($size + 2 * $outerFrame) * $pixelPerPoint;
        $img = imagecreatetruecolor($imageSize, $imageSize);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $imageSize, $imageSize, $white);
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($frame[$y][$x] === 1) {
                    $x1 = ($outerFrame + $x) * $pixelPerPoint;
                    $y1 = ($outerFrame + $y) * $pixelPerPoint;
                    imagefilledrectangle($img, $x1, $y1, $x1 + $pixelPerPoint - 1, $y1 + $pixelPerPoint - 1, $black);
                }
            }
        }
        if ($filename) {
            $result = imagepng($img, $filename);
        } else {
            header('Content-Type: image/png');
            $result = imagepng($img);
        }
        imagedestroy($img);
        return $result;
    }
}
