<?php
declare(strict_types=1);

if (!defined('BINGO_SYSTEM')) { die('Acceso denegado'); }

/**
 * Renderizador de cartones con celdas cuadradas y tipografía escalada (email-safe).
 * Añadido: soporte para grid_style (solid, dotted, dashed, minimalist) y center_content.
 */
final class BingoCardRenderer
{
    /**
     * @param array               $data      Estructura del cartón (B..O o 5x5 o ['numbers'=>...]).
     * @param string              $cardCode  Código del cartón (se muestra debajo).
     * @param array<string,mixed> $settings  Ajustes visuales opcionales.
     * @param string|null         $eventName Nombre del evento para el pie.
     */
    public function renderHtml(array $data, string $cardCode = '', ?array $settings = null, ?string $eventName = null): string
    {
        [$grid] = $this->normalize($data);

        // Configuración visual básica
        $cfg = [
            'header_color'      => $settings['header_color']      ?? '#ef4444',
            'header_text_color' => $settings['header_text_color'] ?? '#ffffff',
            'free_cell_color'   => $settings['free_cell_color']   ?? '#ef4444',
            'free_text_color'   => $settings['free_text_color']   ?? '#ffffff',
            'border_color'      => $settings['border_color']      ?? '#ef4444',
            'number_color'      => $settings['number_color']      ?? '#111827',
            'font_family'       => $settings['font_family']       ?? 'Poppins, Arial, sans-serif',
            'grid_style'        => $settings['grid_style']        ?? 'solid',          // NUEVO
            'center_content'    => $settings['center_content']    ?? 'FREE',           // NUEVO
            // Tamaños/escala
            'cell_size'         => isset($settings['cell_size']) ? (int)$settings['cell_size'] : 96,
            'header_height'     => isset($settings['header_height']) ? (int)$settings['header_height'] : 44,
            'number_scale'      => isset($settings['number_scale']) ? (float)$settings['number_scale'] : 0.50,
            'header_scale'      => isset($settings['header_scale']) ? (float)$settings['header_scale'] : 0.60,
            'free_scale'        => isset($settings['free_scale']) ? (float)$settings['free_scale'] : null,
        ];

        // Límites seguros
        if ($cfg['cell_size'] < 60)  { $cfg['cell_size'] = 60; }
        if ($cfg['cell_size'] > 140) { $cfg['cell_size'] = 140; }
        if ($cfg['header_height'] < 32) { $cfg['header_height'] = 32; }
        $cfg['number_scale'] = max(0.28, min(0.60, $cfg['number_scale']));
        $cfg['header_scale'] = max(0.40, min(0.80, $cfg['header_scale']));
        $freeScale = $cfg['free_scale'];
        if ($freeScale === null) { $freeScale = $cfg['number_scale'] + 0.04; }
        $freeScale = max(0.34, min(0.60, $freeScale));

        // Derivados
        $cell       = $cfg['cell_size'];
        $tableWidth = $cell * 5; // 5 columnas
        $letters    = ['B','I','N','G','O'];

        // Normalizar estilo de borde
        $gridStyle = strtolower((string)$cfg['grid_style']);
        if (!in_array($gridStyle, ['solid','dotted','dashed','minimalist'], true)) {
            $gridStyle = 'solid';
        }
        $cssBorderStyle = $gridStyle === 'minimalist' ? 'solid' : $gridStyle;

        // Tamaños de fuente en px
        $numFontPx    = (int) round($cell * $cfg['number_scale']);               // números
        $freeFontPx   = (int) round($cell * $freeScale);                          // FREE
        $headerFontPx = (int) round($cfg['header_height'] * $cfg['header_scale']); // letras BINGO

        // Estilos inline email-friendly
        $wrapStyle  = sprintf(
            'margin:16px auto; width:%1$dpx; max-width:%1$dpx; font-family:%2$s; color:%3$s;',
            $tableWidth,
            htmlspecialchars($cfg['font_family'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($cfg['number_color'], ENT_QUOTES, 'UTF-8')
        );
        $tableStyle = sprintf(
            'border-collapse:collapse; table-layout:fixed; width:%1$dpx; max-width:%1$dpx; border:1px %2$s %3$s;',
            $tableWidth,
            $cssBorderStyle,
            $cfg['border_color']
        );

        // Base TD/TH
        $baseSquare = sprintf(
            'width:%1$dpx; height:%1$dpx; line-height:%1$dpx; text-align:center; vertical-align:middle; white-space:nowrap;',
            $cell
        );

        // En minimalist solo líneas horizontales, en otros, borde completo
        $tdBorder = ($gridStyle === 'minimalist')
            ? sprintf('border-top:1px %1$s %2$s;', $cssBorderStyle, $cfg['border_color'])
            : sprintf('border:1px %1$s %2$s;',    $cssBorderStyle, $cfg['border_color']);

        $thBorder = ($gridStyle === 'minimalist')
            ? sprintf('border-bottom:1px %1$s %2$s;', $cssBorderStyle, $cfg['border_color'])
            : sprintf('border:1px %1$s %2$s;',        $cssBorderStyle, $cfg['border_color']);

        $thStyle = sprintf(
            '%1$s height:%2$dpx; line-height:%2$dpx; text-align:center; vertical-align:middle; white-space:nowrap;' .
            'background:%3$s; color:%4$s; font-weight:900; text-transform:uppercase; font-size:%5$dpx; letter-spacing:.5px;',
            $thBorder, $cfg['header_height'], $cfg['header_color'], $cfg['header_text_color'], max(12, $headerFontPx)
        );

        $tdNumberStyle = $baseSquare . $tdBorder . sprintf(
            'color:%1$s; font-weight:900; font-size:%2$dpx; letter-spacing:.3px;',
            $cfg['number_color'], max(12, $numFontPx)
        );

        $tdFreeStyle = $baseSquare . $tdBorder . sprintf(
            'color:%1$s; background:%2$s; font-weight:900; text-transform:uppercase; font-size:%3$dpx; letter-spacing:.5px;',
            $cfg['free_text_color'], $cfg['free_cell_color'], max(12, $freeFontPx)
        );

        ob_start();
        ?>
        <div class="bingo-card" style="<?php echo $wrapStyle; ?>">
          <table role="presentation" cellpadding="0" cellspacing="0" style="<?php echo $tableStyle; ?>">
            <colgroup>
              <col style="width:<?php echo $cell; ?>px;">
              <col style="width:<?php echo $cell; ?>px;">
              <col style="width:<?php echo $cell; ?>px;">
              <col style="width:<?php echo $cell; ?>px;">
              <col style="width:<?php echo $cell; ?>px;">
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
                      $val = $grid[$r][$c];
                      $isFree = ($r === 2 && $c === 2 && $this->isFreeValue($val));
                      // Si es FREE, usamos el center_content configurado (texto/emoji) salvo que la celda ya traiga un texto personalizado no vacío.
                      if ($isFree) {
                          $content = (is_string($val) && trim($val) !== '') ? (string)$val : (string)$cfg['center_content'];
                          if (trim($content) === '') { $content = 'FREE'; }
                      } else {
                          $content = (string)$val;
                      }
                      $style = $isFree ? $tdFreeStyle : $tdNumberStyle;
                    ?>
                    <td style="<?php echo $style; ?>"><?php echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8'); ?></td>
                  <?php endfor; ?>
                </tr>
              <?php endfor; ?>
            </tbody>
          </table>

          <?php if ($eventName || $cardCode): ?>
            <div style="text-align:center; font-size:13px; color:#475569; margin-top:10px;">
              <?php if ($eventName): ?>
                <div style="font-weight:700; margin-bottom:2px;"><?php echo htmlspecialchars($eventName, ENT_QUOTES, 'UTF-8'); ?></div>
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

    /** Normaliza a grilla 5x5 (filas). @return array{0: array<int, array<int, string|int>>} */
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
            // De 5x5 a columnas
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

        // FREE al centro si está vacío
        if (!isset($cols['N'][2]) || $cols['N'][2] === '' || $cols['N'][2] === null) {
            $cols['N'][2] = 'FREE';
        }

        // Construir grilla 5x5 (filas)
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