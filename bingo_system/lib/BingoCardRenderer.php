<?php
declare(strict_types=1);

if (!defined('BINGO_SYSTEM')) { die('Acceso denegado'); }

/**
 * Renderizador de cartones con alto nivel de personalización (email-safe).
 *
 * Ajustes soportados (array $settings):
 * - Colores: header_color, header_text_color, free_cell_color, free_text_color, border_color, number_color
 * - Tipografía: font_family
 * - Cuadrícula: grid_style ('solid','dotted','dashed','minimalist')
 * - Centro: center_content (texto/emoji)
 * - Tamaños:
 *     cell_width, cell_height (px)
 *     header_height (px)
 *     number_scale (0.28..0.60)
 *     header_scale (0.40..0.80)
 *     free_scale (opcional; por defecto number_scale+0.04)
 * - Formas de celda:
 *     cell_shape ('square' | 'rounded' | 'circle')
 *     border_radius (px) solo para 'rounded'
 * - Encabezado con degradado:
 *     header_bg_mode ('solid'|'gradient'), header_grad_from, header_grad_to, header_grad_dir (ej: 'to right' o '90deg')
 * - Fondo del bloque:
 *     wrap_bg_mode ('none'|'solid'|'gradient'), wrap_bg_color, wrap_grad_from, wrap_grad_to, wrap_grad_dir
 * - Pie:
 *     event_date (string) opcional para mostrar junto al nombre del evento
 */
final class BingoCardRenderer
{
    public function renderHtml(array $data, string $cardCode = '', ?array $settings = null, ?string $eventName = null): string
    {
        [$grid] = $this->normalize($data);
        $S = $settings ?? [];

        // Defaults compactos y límites
        $cellW  = max(30, min(200, (int)($S['cell_width']     ?? ($S['cell_size'] ?? 48))));
        $cellH  = max(30, min(200, (int)($S['cell_height']    ?? ($S['cell_size'] ?? 48))));
        $hHead  = max(24, min(120, (int)($S['header_height']  ?? 36)));
        $nScale = max(0.28, min(0.60, (float)($S['number_scale'] ?? 0.48)));
        $hScale = max(0.40, min(0.80, (float)($S['header_scale'] ?? 0.60)));
        $fScale = $S['free_scale'] ?? ($nScale + 0.04);
        $fScale = max(0.34, min(0.60, (float)$fScale));

        $letters = ['B','I','N','G','O'];
        $tableW  = $cellW * 5;

        // Estilos base
        $font    = (string)($S['font_family']       ?? 'Poppins, Arial, sans-serif');
        $hFg     = (string)($S['header_text_color'] ?? '#ffffff');
        $hBg     = (string)($S['header_color']      ?? '#ef4444');
        $nCol    = (string)($S['number_color']      ?? '#111827');
        $brCol   = (string)($S['border_color']      ?? '#ef4444');
        $freeBg  = (string)($S['free_cell_color']   ?? '#ef4444');
        $freeFg  = (string)($S['free_text_color']   ?? '#ffffff');

        // Bordes/cuadrícula
        $gridStyle = strtolower((string)($S['grid_style'] ?? 'solid'));
        if (!in_array($gridStyle, ['solid','dotted','dashed','minimalist'], true)) $gridStyle = 'solid';
        $cssBorderStyle = $gridStyle === 'minimalist' ? 'solid' : $gridStyle;

        // Forma
        $shape   = strtolower((string)($S['cell_shape'] ?? 'square')); // square|rounded|circle
        if (!in_array($shape, ['square','rounded','circle'], true)) $shape = 'square';
        $radius  = max(0, min(40, (int)($S['border_radius'] ?? 10)));

        // Encabezado con degradado
        $hMode   = strtolower((string)($S['header_bg_mode'] ?? 'solid')); // solid|gradient
        $hFrom   = (string)($S['header_grad_from'] ?? $hBg);
        $hTo     = (string)($S['header_grad_to']   ?? $hBg);
        $hDir    = (string)($S['header_grad_dir']  ?? 'to right');

        // Fondo del wrapper
        $wMode   = strtolower((string)($S['wrap_bg_mode'] ?? 'none')); // none|solid|gradient
        $wColor  = (string)($S['wrap_bg_color'] ?? '#ffffff');
        $wFrom   = (string)($S['wrap_grad_from'] ?? '#ffffff');
        $wTo     = (string)($S['wrap_grad_to']   ?? '#ffffff');
        $wDir    = (string)($S['wrap_grad_dir']  ?? 'to bottom');

        // Tamaños de fuente derivados
        $numFontPx    = (int) round($cellH * $nScale);
        $freeFontPx   = (int) round($cellH * $fScale);
        $headerFontPx = (int) round($hHead * $hScale);

        // Estilos wrapper
        $wrapBg = '';
        if ($wMode === 'solid') {
            $wrapBg = "background: {$wColor};";
        } elseif ($wMode === 'gradient') {
            $wrapBg = "background: {$wFrom}; background-image: linear-gradient({$wDir}, {$wFrom}, {$wTo});";
        }
        $wrapStyle = sprintf(
            'margin:12px auto; width:%1$dpx; max-width:%1$dpx; font-family:%2$s; color:%3$s; %4$s',
            $tableW,
            htmlspecialchars($font, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($nCol, ENT_QUOTES, 'UTF-8'),
            $wrapBg
        );

        // Tabla
        $tableStyle = sprintf(
            'border-collapse:collapse; table-layout:fixed; width:%1$dpx; max-width:%1$dpx; border:1px %2$s %3$s;',
            $tableW, $cssBorderStyle, $brCol
        );

        // Bordes de TH/TD (alineación perfecta)
        $tdBorder = ($gridStyle === 'minimalist')
            ? sprintf('border-top:1px %1$s %2$s;', $cssBorderStyle, $brCol)
            : sprintf('border:1px %1$s %2$s;',    $cssBorderStyle, $brCol);
        $thBorder = ($gridStyle === 'minimalist')
            ? sprintf('border-bottom:1px %1$s %2$s;', $cssBorderStyle, $brCol)
            : sprintf('border:1px %1$s %2$s;',        $cssBorderStyle, $brCol);

        // Encabezado: sólido o gradiente
        $headerBg = ($hMode === 'gradient')
            ? "background: {$hFrom}; background-image: linear-gradient({$hDir}, {$hFrom}, {$hTo});"
            : "background: {$hBg};";

        $thStyle = sprintf(
            '%1$s height:%2$dpx; line-height:%2$dpx; text-align:center; vertical-align:middle; white-space:nowrap;' .
            '%3$s color:%4$s; font-weight:900; text-transform:uppercase; font-size:%5$dpx; letter-spacing:.5px;',
            $thBorder, $hHead, $headerBg, $hFg, max(12, $headerFontPx)
        );

        // TD “base”: ajusta ancho y alto; centrado vertical por line-height
        $tdBase = sprintf(
            'width:%1$dpx; height:%2$dpx; line-height:%2$dpx; text-align:center; vertical-align:middle; white-space:nowrap;',
            $cellW, $cellH
        );

        $tdNumberStyle = $tdBase . $tdBorder . sprintf(
            'color:%1$s; font-weight:900; font-size:%2$dpx; letter-spacing:.3px;',
            $nCol, max(12, $numFontPx)
        );

        $tdFreeStyle = $tdBase . $tdBorder . sprintf(
            'color:%1$s; background:%2$s; font-weight:900; text-transform:uppercase; font-size:%3$dpx; letter-spacing:.5px;',
            $freeFg, $freeBg, max(12, $freeFontPx)
        );

        // Para formas "rounded" y "circle" renderizamos un wrapper interno
        $needsInner = ($shape === 'rounded' || $shape === 'circle');
        $innerDiameter = min($cellW, $cellH);

        ob_start();
        ?>
        <div class="bingo-card" style="<?php echo $wrapStyle; ?>">
          <table role="presentation" cellpadding="0" cellspacing="0" style="<?php echo $tableStyle; ?>">
            <colgroup>
              <col style="width:<?php echo $cellW; ?>px;">
              <col style="width:<?php echo $cellW; ?>px;">
              <col style="width:<?php echo $cellW; ?>px;">
              <col style="width:<?php echo $cellW; ?>px;">
              <col style="width:<?php echo $cellW; ?>px;">
            </colgroup>
            <thead>
              <tr>
                <?php foreach ($letters as $L): ?>
                  <th style="<?php echo $thStyle; ?>"><?php echo htmlspecialchars($L, ENT_QUOTES, 'UTF-8'); ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php for ($r=0; $r<5; $r++): ?>
                <tr>
                  <?php for ($c=0; $c<5; $c++): ?>
                    <?php
                      $val    = $grid[$r][$c];
                      $isFree = ($r === 2 && $c === 2 && $this->isFreeValue($val));
                      $content = $isFree
                        ? ((is_string($val) && trim($val) !== '') ? (string)$val : (string)($S['center_content'] ?? 'FREE'))
                        : (string)$val;
                      $style = $isFree ? $tdFreeStyle : $tdNumberStyle;

                      // Render directo (square) o con wrapper interno (rounded/circle)
                      if (!$needsInner) {
                        $cellHtml = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
                      } else {
                        // Wrapper interno centrado (fallback email-safe con line-height)
                        $diam   = $innerDiameter;
                        $innerBg = $isFree ? $freeBg : 'transparent';
                        $innerFg = $isFree ? $freeFg : $nCol;
                        $innerBorder = "1px {$cssBorderStyle} {$brCol}";
                        $innerRadius = ($shape === 'circle') ? '50%' : ($radius . 'px');
                        $innerFont   = ($isFree ? max(12, (int)round($diam * $fScale)) : max(12, (int)round($diam * $nScale)));

                        $cellHtml = sprintf(
                          '<div style="margin:0 auto; width:%1$dpx; height:%1$dpx; line-height:%1$dpx; border-radius:%2$s; ' .
                          'border:%3$s; background:%4$s; color:%5$s; font-weight:900; font-size:%6$dpx;">%7$s</div>',
                          $diam,
                          $innerRadius,
                          $innerBorder,
                          $innerBg,
                          $innerFg,
                          $innerFont,
                          htmlspecialchars($content, ENT_QUOTES, 'UTF-8')
                        );

                        // Para que el borde del wrapper reemplace el borde del TD (evita doble borde visual):
                        // En este caso quitamos el borde del TD y lo dejamos solo en el wrapper interno.
                        $style = str_replace(['border:1px','border-top:1px'], ['/*border:1px','/*border-top:1px'], $style);
                      }
                    ?>
                    <td style="<?php echo $style; ?>"><?php echo $cellHtml; ?></td>
                  <?php endfor; ?>
                </tr>
              <?php endfor; ?>
            </tbody>
          </table>

          <?php if ($eventName || $cardCode): ?>
            <div style="text-align:center; font-size:13px; color:#475569; margin-top:8px;">
              <?php if ($eventName): ?>
                <div style="font-weight:700; margin-bottom:2px;">
                  <?php
                    $line = htmlspecialchars($eventName, ENT_QUOTES, 'UTF-8');
                    if (!empty($S['event_date'])) {
                      $line .= ' · ' . htmlspecialchars((string)$S['event_date'], ENT_QUOTES, 'UTF-8');
                    }
                    echo $line;
                  ?>
                </div>
              <?php endif; ?>
              <?php if ($cardCode): ?>
                <div>Código: <strong><?php echo htmlspecialchars($cardCode, ENT_QUOTES, 'UTF-8'); ?></strong></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        <?php
        return trim((string)ob_get_clean());
    }

    private function normalize(array $data): array
    {
        if (isset($data['numbers']) && is_array($data['numbers'])) {
            $data = $data['numbers'];
        }
        if (isset($data['B'],$data['I'],$data['N'],$data['G'],$data['O'])) {
            $cols = [
                'B' => array_values($data['B']),
                'I' => array_values($data['I']),
                'N' => array_values($data['N']),
                'G' => array_values($data['G']),
                'O' => array_values($data['O']),
            ];
        } else {
            $cols = ['B'=>[], 'I'=>[], 'N'=>[], 'G'=>[], 'O'=>[]];
            for ($r=0; $r<5; $r++) {
                $row = array_values($data[$r] ?? []);
                for ($c=0; $c<5; $c++) {
                    $val = $row[$c] ?? '';
                    if ($c === 0) $cols['B'][$r] = $val;
                    if ($c === 1) $cols['I'][$r] = $val;
                    if ($c === 2) $cols['N'][$r] = $val;
                    if ($c === 3) $cols['G'][$r] = $val;
                    if ($c === 4) $cols['O'][$r] = $val;
                }
            }
        }
        if (!isset($cols['N'][2]) || $cols['N'][2] === '' || $cols['N'][2] === null) {
            $cols['N'][2] = 'FREE';
        }
        $grid = [];
        for ($r=0; $r<5; $r++) {
            $grid[$r] = [
                $cols['B'][$r] ?? '',
                $cols['I'][$r] ?? '',
                $cols['N'][$r] ?? '',
                $cols['G'][$r] ?? '',
                $cols['O'][$r] ?? '',
            ];
        }
        return [$grid];
    }

    private function isFreeValue($val): bool
    {
        if ($val === null) return true;
        if ($val === '') return true;
        $s = strtoupper(trim((string)$val));
        return ($s === 'FREE' || $s === 'LIBRE' || $s === '★' || $s === '⭐' || $s === '✨' || $s === '🎉' || $s === '💎');
    }
}