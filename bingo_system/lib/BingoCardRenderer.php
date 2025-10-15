<?php
if (!defined('BINGO_SYSTEM')) die('Acceso denegado');

class BingoCardRenderer
{
    private array $defaults = [
        'header_color'      => '#ef4444',
        'header_text_color' => '#ffffff',
        'free_cell_color'   => '#ef4444',
        'free_text_color'   => '#ffffff',
        'grid_style'        => 'solid', // solid | dashed
        'border_color'      => '#ef4444',
        'number_color'      => '#2d3748',
        'font_family'       => 'Poppins, Arial, sans-serif',
        'center_content'    => 'FREE',
    ];

    /**
     * Render compatible con correos (tabla HTML con estilos inline).
     * $cardData puede venir como:
     *  - ['numbers' => ['B'=>[...], 'I'=>...]]
     *  - ['B'=>[...], 'I'=>..., 'N'=>..., 'G'=>..., 'O'=>...]
     *  - matriz 5x5 de filas
     */
    public function renderHtml($cardData, string $cardCode, ?array $settings = null, ?string $eventName = null): string
    {
        $cfg = array_merge($this->defaults, $settings ?? []);
        $grid = $this->normalize($cardData, (string)$cfg['center_content']);

        $tableBorder = $cfg['grid_style'] === 'dashed'
            ? "2px dashed {$cfg['border_color']}"
            : "2px solid {$cfg['border_color']}";
        $cellBorder  = $cfg['grid_style'] === 'dashed'
            ? "1px dashed {$cfg['border_color']}"
            : "1px solid {$cfg['border_color']}";

        // Wrapper
        $html  = '<div style="max-width:360px;margin:12px auto;padding:10px;border:1px solid #e5e7eb;border-radius:12px;font-family:' . htmlspecialchars((string)$cfg['font_family']) . ';">';

        // Tabla principal (usar tablas para compatibilidad con clientes de correo)
        $html .= '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:separate;border-spacing:0;">';

        // Encabezado B I N G O
        $html .= '<tr>';
        $letters = ['B','I','N','G','O'];
        foreach ($letters as $i => $letter) {
            $style = 'background:' . htmlspecialchars($cfg['header_color']) . ';'
                   . 'color:' . htmlspecialchars($cfg['header_text_color']) . ';'
                   . 'text-transform:uppercase;font-weight:900;text-align:center;padding:10px 0;'
                   . 'border-top:' . $tableBorder . ';'
                   . 'border-left:' . ($i === 0 ? $tableBorder : $cellBorder) . ';'
                   . 'border-right:' . ($i === 4 ? $tableBorder : $cellBorder) . ';';
            $html .= '<td style="' . $style . '">' . $letter . '</td>';
        }
        $html .= '</tr>';

        // Celdas 5x5
        for ($r = 0; $r < 5; $r++) {
            $html .= '<tr>';
            for ($c = 0; $c < 5; $c++) {
                $val = $grid[$r][$c];
                $isCenter = ($r === 2 && $c === 2);
                $isFree   = $this->isFree($val);

                $base  = 'text-align:center;font-weight:700;font-size:16px;padding:12px 0;background:#ffffff;';
                $base .= 'border-left:' . ($c === 0 ? $tableBorder : $cellBorder) . ';';
                $base .= 'border-right:' . ($c === 4 ? $tableBorder : $cellBorder) . ';';
                $base .= 'border-bottom:' . ($r === 4 ? $tableBorder : $cellBorder) . ';';

                if ($isCenter && $isFree) {
                    $style = $base
                        . 'background:' . htmlspecialchars($cfg['free_cell_color']) . ';'
                        . 'color:' . htmlspecialchars($cfg['free_text_color']) . ';';
                    $content = htmlspecialchars(is_string($val) && trim((string)$val) !== '' ? (string)$val : (string)$cfg['center_content']);
                } else {
                    $style = $base . 'color:' . htmlspecialchars($cfg['number_color']) . ';';
                    $content = htmlspecialchars((string)$val);
                }
                $html .= '<td style="' . $style . '">' . $content . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</table>';

        // Pie: evento y código
        $html .= '<div style="text-align:center;margin-top:10px;">';
        if (!empty($eventName)) {
            $html .= '<div style="font-size:12px;color:#6b7280;margin-bottom:6px;">' . htmlspecialchars((string)$eventName) . '</div>';
        }
        $html .= '<div style="font-size:12px;color:#6b7280;margin-bottom:4px;">Tu código único de cartón:</div>';
        $html .= '<span style="display:inline-block;padding:6px 10px;border-radius:8px;border:1px solid #d1d5db;background:#f9fafb;font-weight:700;letter-spacing:1px;">'
              . htmlspecialchars($cardCode) . '</span>';
        $html .= '</div>';

        $html .= '</div>';
        return $html;
    }

    /**
     * Normaliza la estructura del cartón a una matriz 5x5 de filas.
     */
    private function normalize($data, string $centerContent): array
    {
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
            if (!isset($grid[2][2]) || $grid[2][2] === '' || $grid[2][2] === null) {
                $grid[2][2] = $centerContent;
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
                $grid[2][2] = $centerContent;
            }
            return $grid;
        }

        // Fallback
        $grid = array_fill(0, 5, array_fill(0, 5, ''));
        $grid[2][2] = $centerContent;
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
}