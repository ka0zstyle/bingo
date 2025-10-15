<?php
if (!defined('BINGO_SYSTEM')) die('Acceso denegado');

class BingoCardGenerator
{
    private ?PDO $pdo;

    /**
     * @param PDO|null $pdo Inyéctalo si deseas usar la DB para checks de unicidad más adelante.
     */
    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * Genera un cartón de bingo con columnas y un código.
     * Formato retornado:
     * [
     *   'code'    => 'B-1166-K',
     *   'numbers' => [
     *      'B' => [5 valores entre 1..15],
     *      'I' => [5 valores entre 16..30],
     *      'N' => [5 valores entre 31..45] (índice 2 puede ser 'FREE' si $centerIsFree),
     *      'G' => [5 valores entre 46..60],
     *      'O' => [5 valores entre 61..75],
     *   ],
     * ]
     */
    public function generate(bool $centerIsFree = true): array
    {
        $numbers = [
            'B' => $this->pickUnique(1, 15, 5),
            'I' => $this->pickUnique(16, 30, 5),
            'N' => $this->pickUnique(31, 45, 5),
            'G' => $this->pickUnique(46, 60, 5),
            'O' => $this->pickUnique(61, 75, 5),
        ];

        // Ordena cada columna ascendentemente para una estética consistente
        foreach ($numbers as $k => $col) {
            sort($col, SORT_NUMERIC);
            $numbers[$k] = array_values($col);
        }

        // Centro libre si aplica (columna N, índice 2)
        if ($centerIsFree) {
            $numbers['N'][2] = 'FREE';
        }

        // Generar un código legible
        $code = $this->generateCode();

        return [
            'code'    => $code,
            'numbers' => $numbers,
        ];
    }

    /**
     * Elige $count números únicos entre $min y $max (incluyentes).
     */
    private function pickUnique(int $min, int $max, int $count): array
    {
        // Genera la lista completa y barájala, luego toma $count
        $pool = range($min, $max);
        shuffle($pool);
        return array_slice($pool, 0, $count);
    }

    /**
     * Genera un código de cartón legible. La unicidad global no está garantizada,
     * pero para el flujo actual es suficiente; la capa de persistencia puede
     * reemplazarlo si tiene otra convención (como en procesar_compra.php).
     *
     * Ejemplo: B-72351-Q
     */
    private function generateCode(): string
    {
        // Combinación: prefijo 'B', 5 dígitos pseudo-aleatorios y una letra
        $num = random_int(10000, 99999);
        $chr = chr(random_int(65, 90)); // A-Z
        return "B-{$num}-{$chr}";
    }
}