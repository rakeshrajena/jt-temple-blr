<?php
declare(strict_types=1);

/**
 * Small single-purpose PDF writer (Helvetica only). No extensions required.
 */
final class PdfDocument
{
    private float $pageWidth;
    private float $pageHeight;
    /** @var list<string> */
    private array $pages = [];
    private string $ops = '';
    /** @var list<array{width:int,height:int,jpeg:?string,rgb:string,alpha:?string,colorSpace:string}> */
    private array $images = [];
    /** @var array<string, float> */
    private array $opacities = [];

    public function __construct(float $pageWidth, float $pageHeight)
    {
        $this->pageWidth = $pageWidth;
        $this->pageHeight = $pageHeight;
    }

    public function addPage(): void
    {
        $this->pages[] = $this->ops;
        $this->ops = '';
    }

    public function setFill(float $r, float $g, float $b): void
    {
        $this->ops .= sprintf("%.3F %.3F %.3F rg\n", $r, $g, $b);
    }

    public function setStroke(float $r, float $g, float $b): void
    {
        $this->ops .= sprintf("%.3F %.3F %.3F RG\n", $r, $g, $b);
    }

    public function setLineWidth(float $width): void
    {
        $this->ops .= sprintf("%.2F w\n", $width);
    }

    public function setDash(?float $on = null, ?float $off = null): void
    {
        if ($on === null || $off === null) {
            $this->ops .= "[] 0 d\n";
            return;
        }
        $this->ops .= sprintf("[%.2F %.2F] 0 d\n", $on, $off);
    }

    public function rect(float $x, float $y, float $w, float $h, bool $stroke = true, bool $fill = false): void
    {
        $op = ($stroke && $fill) ? 'B' : ($fill ? 'f' : 'S');
        $this->ops .= sprintf("%.2F %.2F %.2F %.2F re %s\n", $x, $y, $w, $h, $op);
    }

    public function line(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->ops .= sprintf("%.2F %.2F m %.2F %.2F l S\n", $x1, $y1, $x2, $y2);
    }

    public function text(float $x, float $y, string $text, float $size, string $font = 'F1'): void
    {
        $escaped = $this->escape($text);
        $this->ops .= sprintf(
            "BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $font,
            $size,
            $x,
            $y,
            $escaped
        );
    }

    public function textWidth(string $text, float $size, bool $bold = false): float
    {
        $width = 0;
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($chars as $ch) {
            $width += $this->charWidth($ch, $bold);
        }
        return $width * $size / 1000;
    }

    /** @return list<string> */
    public function wrap(string $text, float $size, float $maxWidth, bool $bold = false): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if ($this->textWidth($candidate, $size, $bold) <= $maxWidth) {
                $current = $candidate;
                continue;
            }
            if ($current !== '') {
                $lines[] = $current;
            }
            $current = $word;
        }
        if ($current !== '') {
            $lines[] = $current;
        }
        return $lines;
    }

    /**
     * @param array{width:int,height:int,jpeg?:?string,rgb?:string,alpha?:?string,colorSpace?:string} $image
     */
    public function addImage(array $image): int
    {
        $this->images[] = [
            'width' => (int) $image['width'],
            'height' => (int) $image['height'],
            'jpeg' => isset($image['jpeg']) && is_string($image['jpeg']) && $image['jpeg'] !== '' ? $image['jpeg'] : null,
            'rgb' => (string) ($image['rgb'] ?? ''),
            'alpha' => isset($image['alpha']) && is_string($image['alpha']) && $image['alpha'] !== '' ? $image['alpha'] : null,
            'colorSpace' => (string) ($image['colorSpace'] ?? 'DeviceRGB'),
        ];
        return count($this->images) - 1;
    }

    public function drawImage(int $index, float $x, float $y, float $w, float $h, float $opacity = 1.0): void
    {
        if (!isset($this->images[$index]) || $w <= 0.0 || $h <= 0.0) {
            return;
        }
        $this->ops .= "q\n";
        if ($opacity < 0.999) {
            $key = number_format(max(0.05, min(1.0, $opacity)), 2, '.', '');
            $this->opacities[$key] = (float) $key;
            $this->ops .= '/GS' . str_replace('.', '', $key) . " gs\n";
        }
        $this->ops .= sprintf("%.2F 0 0 %.2F %.2F %.2F cm\n/Im%d Do\nQ\n", $w, $h, $x, $y, $index);
    }

    /** Draws a Code 128 module string. 1 is a bar and 0 is a space. */
    public function bars(float $x, float $y, float $width, float $height, string $modules): void
    {
        $count = strlen($modules);
        if ($count < 1 || $width <= 0.0 || $height <= 0.0) {
            return;
        }
        $module = $width / $count;
        $this->setFill(0, 0, 0);
        $index = 0;
        while ($index < $count) {
            if ($modules[$index] !== '1') {
                $index++;
                continue;
            }
            $start = $index;
            while ($index < $count && $modules[$index] === '1') {
                $index++;
            }
            $this->rect($x + ($start * $module), $y, ($index - $start) * $module, $height, false, true);
        }
    }

    public function fitText(string $text, float $size, float $maxWidth, bool $bold = false): string
    {
        $text = trim($text);
        if ($text === '' || $this->textWidth($text, $size, $bold) <= $maxWidth) {
            return $text;
        }
        $suffix = '...';
        while ($text !== '' && $this->textWidth($text . $suffix, $size, $bold) > $maxWidth) {
            $text = mb_substr($text, 0, -1);
        }
        return $text . $suffix;
    }

    public function save(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create PDF directory.');
        }
        if (file_put_contents($path, $this->render()) === false) {
            throw new RuntimeException('Could not write PDF.');
        }
    }

    public function render(): string
    {
        $pages = $this->pages;
        $pages[] = $this->ops;

        $objects = [];
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";
        $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique >>";

        $next = 6;
        $gsIds = [];
        foreach ($this->opacities as $key => $value) {
            $id = $next++;
            $gsIds[$key] = $id;
            $objects[$id] = sprintf('<< /Type /ExtGState /ca %.2F /CA %.2F >>', $value, $value);
        }
        $imageIds = [];
        foreach ($this->images as $index => $image) {
            $maskId = null;
            if ($image['alpha'] !== null) {
                $mask = zlib_encode($image['alpha'], ZLIB_ENCODING_DEFLATE);
                if ($mask === false) {
                    throw new RuntimeException('Could not prepare the logo.');
                }
                $maskId = $next++;
                $objects[$maskId] = $this->streamObject(
                    sprintf(
                        '<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length %d >>',
                        $image['width'],
                        $image['height'],
                        strlen($mask)
                    ),
                    $mask
                );
            }
            if ($image['jpeg'] !== null) {
                $data = $image['jpeg'];
                $dict = sprintf(
                    '<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /%s /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>',
                    $image['width'],
                    $image['height'],
                    $image['colorSpace'],
                    strlen($data)
                );
            } else {
                $data = zlib_encode($image['rgb'], ZLIB_ENCODING_DEFLATE);
                if ($data === false) {
                    throw new RuntimeException('Could not prepare the logo.');
                }
                $maskRef = $maskId !== null ? ' /SMask ' . $maskId . ' 0 R' : '';
                $dict = sprintf(
                    '<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length %d%s >>',
                    $image['width'],
                    $image['height'],
                    strlen($data),
                    $maskRef
                );
            }
            $id = $next++;
            $imageIds[$index] = $id;
            $objects[$id] = $this->streamObject($dict, $data);
        }
        $resources = '/Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >>';
        if ($imageIds !== []) {
            $parts = [];
            foreach ($imageIds as $index => $id) {
                $parts[] = '/Im' . $index . ' ' . $id . ' 0 R';
            }
            $resources .= ' /XObject << ' . implode(' ', $parts) . ' >>';
        }
        if ($gsIds !== []) {
            $parts = [];
            foreach ($gsIds as $key => $id) {
                $parts[] = '/GS' . str_replace('.', '', $key) . ' ' . $id . ' 0 R';
            }
            $resources .= ' /ExtGState << ' . implode(' ', $parts) . ' >>';
        }

        $pageIds = [];
        foreach ($pages as $content) {
            $pageId = $next++;
            $contentId = $next++;
            $pageIds[] = $pageId;
            $objects[$contentId] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
            $objects[$pageId] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Contents %d 0 R /Resources << %s >> >>",
                $this->pageWidth,
                $this->pageHeight,
                $contentId,
                $resources
            );
        }

        $kids = implode(' ', array_map(static fn (int $id): string => $id . ' 0 R', $pageIds));
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Count " . count($pageIds) . " /Kids [" . $kids . "] >>";

        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        $maxId = max(array_keys($objects));
        for ($id = 1; $id <= $maxId; $id++) {
            $offsets[$id] = strlen($pdf);
            $body = $objects[$id] ?? 'null';
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }

    private function streamObject(string $dict, string $data): string
    {
        return $dict . "\nstream\n" . $data . "\nendstream";
    }

    private function escape(string $text): string
    {
        $clean = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $clean);
    }

    private function charWidth(string $ch, bool $bold): int
    {
        static $map = [
            ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667,
            "'" => 191, '(' => 333, ')' => 333, '*' => 389, '+' => 584, ',' => 278, '-' => 333,
            '.' => 278, '/' => 278, '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556,
            '5' => 556, '6' => 556, '7' => 556, '8' => 556, '9' => 556, ':' => 278, ';' => 278,
            '<' => 584, '=' => 584, '>' => 584, '?' => 556, '@' => 1015,
            'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778,
            'H' => 722, 'I' => 278, 'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722,
            'O' => 778, 'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722,
            'V' => 667, 'W' => 944, 'X' => 667, 'Y' => 667, 'Z' => 611,
            'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556,
            'h' => 556, 'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222, 'm' => 833, 'n' => 556,
            'o' => 556, 'p' => 556, 'q' => 556, 'r' => 333, 's' => 500, 't' => 278, 'u' => 556,
            'v' => 500, 'w' => 722, 'x' => 500, 'y' => 500, 'z' => 500,
        ];
        $width = $map[$ch] ?? 500;
        return $bold ? (int) round($width * 1.05) : $width;
    }
}
