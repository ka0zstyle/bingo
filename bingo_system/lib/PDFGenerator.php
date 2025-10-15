<?php
if (!defined('BINGO_SYSTEM')) die('Acceso denegado');

require_once __DIR__ . '/fpdf/fpdf.php';

class PDFGenerator extends FPDF
{
    private array $settings = [];
    private string $eventName = '';

    public function __construct(?array $settings = null)
    {
        parent::__construct('P', 'mm', 'A4');

        // Defaults seguros (evitan warnings por claves faltantes)
        $defaults = [
            'header_color'      => '#ef4444',
            'header_text_color' => '#ffffff',
            'free_cell_color'   => '#ef4444',
            'free_text_color'   => '#ffffff',
            'grid_style'        => 'solid',     // solid | dashed
            'border_color'      => '#ef4444',
            'number_color'      => '#2d3748',
            // FPDF core fonts válidas: Arial, Times, Courier, Symbol, ZapfDingbats
            'font_family'       => 'Arial',
            'center_content'    => 'FREE',
        ];

        $this->settings = array_merge($defaults, is_array($settings) ? $settings : []);

        // Fallback por si pasan una fuente no compatible con FPDF
        $validFonts = ['Arial','Times','Courier','Symbol','ZapfDingbats'];
        if (!in_array($this->settings['font_family'], $validFonts, true)) {
            $this->settings['font_family'] = 'Arial';
        }

        // Márgenes base
        $this->SetMargins(10, 10, 10);
        $this->SetAutoPageBreak(true, 12);
    }

    public function setEventName(?string $name): void
    {
        $this->eventName = $name ? (string)$name : '';
    }

    public function Header(): void
    {
        if ($this->eventName !== '') {
            $this->SetFont($this->settings['font_family'], 'B', 12);
            $this->SetTextColor(45, 55, 72); // #2d3748
            $this->Cell(0, 10, $this->enc($this->eventName), 0, 1, 'C');
            $this->Ln(2);
        }
    }

    public function Footer(): void
    {
        // Pie de página opcional
    }

    /**
     * Dibuja un cartón de bingo a partir de números normalizados.
     * Soporta formatos:
     *  - ['numbers' => ['B'=>[...], 'I'=>...]]  (formato antiguo)
     *  - ['B'=>[...], 'I'=>..., 'N'=>..., 'G'=>..., 'O'=>...] (columnas)
     *  - Matriz 5x5 (filas)
     */
    public function DrawBingoCard($cardData, string $cardCode): void
    {
        // Normalizar entrada a grid 5x5 (filas)
        $grid = $this->normalizeToGrid($cardData);

        // Dimensiones del cartón
        $cardWidth   = 100;            // mm
        $cellW       = $cardWidth / 5; // ancho por columna
        $cellH       = 12;             // alto de celda
        $headerH     = 12;
        $codeBoxH    = 10;
        $paddingTop  = 2;
        $startX      = (210 - $cardWidth) / 2; // centrar en A4 horizontalmente
        $startY      = $this->GetY() + $paddingTop;

        // Colores
        [$hr, $hg, $hb] = $this->hexToRgb($this->settings['header_color']);
        [$htr,$htg,$htb]= $this->hexToRgb($this->settings['header_text_color']);
        [$fr, $fg, $fb] = $this->hexToRgb($this->settings['free_cell_color']);
        [$ftr,$ftg,$ftb]= $this->hexToRgb($this->settings['free_text_color']);
        [$br, $bg, $bb] = $this->hexToRgb($this->settings['border_color']);
        [$nr, $ng, $nb] = $this->hexToRgb($this->settings['number_color']);

        // Estilo de borde (línea discontinua opcional)
        $dash = ($this->settings['grid_style'] === 'dashed');

        // Header BINGO
        $this->SetDrawColor($br, $bg, $bb);
        $this->SetFillColor($hr, $hg, $hb);
        $this->SetTextColor($htr, $htg, $htb);
        $this->SetLineWidth(0.6);
        $this->SetFont($this->settings['font_family'], 'B', 16);

        $letters = ['B','I','N','G','O'];
        for ($i = 0; $i < 5; $i++) {
            $x = $startX + $i * $cellW;
            $y = $startY;
            $this->Rect($x, $y, $cellW, $headerH, 'F');
            $this->setDash($dash);
            $this->Rect($x, $y, $cellW, $headerH);
            $this->setDash(false);

            $this->SetXY($x, $y + 3);
            $this->Cell($cellW, $headerH - 6, $letters[$i], 0, 0, 'C');
        }

        // Celdas
        $this->SetFont($this->settings['font_family'], '', 12);
        $this->SetTextColor($nr, $ng, $nb);
        $this->SetLineWidth(0.3);

        for ($row = 0; $row < 5; $row++) {
            for ($col = 0; $col < 5; $col++) {
                $x = $startX + $col * $cellW;
                $y = $startY + $headerH + $row * $cellH;

                $val = $grid[$row][$col];

                $isCenter = ($row === 2 && $col === 2);
                $isFree   = $this->isFree($val);

                // Fondo de celda
                if ($isCenter && $isFree) {
                    $this->SetFillColor($fr, $fg, $fb);
                    $this->Rect($x, $y, $cellW, $cellH, 'F');
                    $this->SetTextColor($ftr, $ftg, $ftb);
                } else {
                    $this->SetFillColor(255, 255, 255);
                    $this->Rect($x, $y, $cellW, $cellH, 'F');
                    $this->SetTextColor($nr, $ng, $nb);
                }

                $this->setDash($dash);
                $this->Rect($x, $y, $cellW, $cellH);
                $this->setDash(false);

                // Texto centrado
                $this->SetXY($x, $y + 3);
                $txt = $isCenter && $isFree
                    ? (is_string($val) && trim($val) !== '' ? $val : $this->settings['center_content'])
                    : (string)$val;

                $this->Cell($cellW, $cellH - 6, $this->enc($txt), 0, 0, 'C');
            }
        }

        // Código del cartón
        $this->Ln(5);
        $this->SetXY($startX, $startY + $headerH + 5 * $cellH + 3);
        $this->SetTextColor(107, 114, 128); // #6b7280
        $this->SetFont($this->settings['font_family'], '', 9);
        $this->Cell($cardWidth, 5, $this->enc('Tu código único de cartón:'), 0, 2, 'C');

        $this->SetTextColor(45, 55, 72);
        $this->SetDrawColor(209, 213, 219);  // #d1d5db
        $this->SetFillColor(249, 250, 251);  // #f9fafb
        $this->SetLineWidth(0.3);
        $this->Cell($cardWidth, $codeBoxH, $this->enc($cardCode), 1, 1, 'C', true);

        // Avanza el cursor para dejar espacio entre cartones
        $this->Ln(6);
    }

    /**
     * Normaliza datos a matriz 5x5 (filas).
     */
    private function normalizeToGrid($data): array
    {
        // Si viene envuelto en ["numbers" => ...]
        if (is_array($data) && array_key_exists('numbers', $data)) {
            $data = $data['numbers'];
        }

        // Columnas B..O
        if (is_array($data) && isset($data['B'],$data['I'],$data['N'],$data['G'],$data['O'])) {
            $grid = [];
            for ($r = 0; $r < 5; $r++) {
                $grid[$r] = [
                    $data['B'][$r] ?? '',
                    $data['I'][$r] ?? '',
                    $data['N'][$r] ?? '',
                    $data['G'][$r] ?? '',
                    $data['O'][$r] ?? '',
                ];
            }
            // Centro FREE si está vacío
            if (!isset($grid[2][2]) || $grid[2][2] === '' || $grid[2][2] === null) {
                $grid[2][2] = $this->settings['center_content'];
            }
            return $grid;
        }

        // 5x5 directo
        if (is_array($data) && count($data) === 5 && is_array($data[0] ?? null)) {
            $grid = [];
            for ($r = 0; $r < 5; $r++) {
                $row = array_values($data[$r]);
                for ($c = 0; $c < 5; $c++) {
                    if (!array_key_exists($c, $row)) $row[$c] = '';
                }
                $grid[$r] = array_slice($row, 0, 5);
            }
            if (!isset($grid[2][2]) || $grid[2][2] === '' || $grid[2][2] === null) {
                $grid[2][2] = $this->settings['center_content'];
            }
            return $grid;
        }

        // Fallback
        $grid = array_fill(0, 5, array_fill(0, 5, ''));
        $grid[2][2] = $this->settings['center_content'];
        return $grid;
    }

    private function isFree($val): bool
    {
        if (is_string($val)) {
            $v = strtoupper(trim($val));
            return in_array($v, ['FREE','LIBRE','X','⭐','💎'], true);
        }
        return false;
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $r = hexdec(str_repeat($hex[0], 2));
            $g = hexdec(str_repeat($hex[1], 2));
            $b = hexdec(str_repeat($hex[2], 2));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        return [$r,$g,$b];
    }

    private function setDash(bool $enable): void
    {
        // Patrón de línea discontinua (si el PDF viewer lo soporta)
        if ($enable) {
            $this->_out(sprintf('%.2F %.2F d', 3, 3)); // dash 3 on, 3 off
        } else {
            $this->_out('[] 0 d'); // sólido
        }
    }

    // Convierte a CP1252 de forma segura para FPDF
    private function enc($str): string
    {
        $s = (string)$str;
        if ($s === '') return '';
        if (function_exists('mb_convert_encoding')) {
            $out = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
            if ($out !== false && $out !== null) return $out;
        }
        if (function_exists('iconv')) {
            $out = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
            if ($out !== false && $out !== null) return $out;
        }
        // Último recurso: filtrar caracteres no ASCII
        return preg_replace('/[^\x20-\x7E]/', '?', $s);
    }
}