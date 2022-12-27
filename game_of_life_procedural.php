<?php
declare (strict_types = 1);
/**
 * @psalm-type Matrix = bool[][]
 */
function cell_will_live(bool $is_alive, int $c_alive_near): bool{
    // - Qualsiasi cella viva con due o tre celle vive adiacenti sopravvive alla generazione successiva;
    $healthy = $is_alive && in_array($c_alive_near, [2, 3]);
    // - Qualsiasi cella morta con esattamente tre celle vive adiacenti diventa una cella viva, come per effetto di riproduzione.
    $reproduction = !$is_alive && $c_alive_near === 3;
    if ($healthy || $reproduction) {
        return true;
    }
    // death is the most obvious outcome
    return false;
}
function cell_render(bool $is_alive): string {
    return $is_alive ? '#' : '.';
}
/** @param Matrix $matrix  */
function grid_render(array $matrix): string{
    $str = '';
    foreach ($matrix as $x => $row) {
        foreach ($row as $y => $is_alive) {
            $str .= $cell = cell_render($is_alive);
        }
        $str .= "\n";
    }
    return $str;
}
// elimna output dallo screen
function grid_clear(): void{
    system('clear');
}
/**
 * @param Matrix $matrix
 * @return bool|null
 */
function matrix_at(array $matrix, int $x, int $y) {
    return isset($matrix[$x][$y]) ? $matrix[$x][$y] : null;
}
/**
 * count 4/8 adjacent cells that are alive
 * @param Matrix $matrix
 */
function matrix_count_near_alive(array $matrix, int $x, int $y): int{
    /** @var  array<int, null|ICell> $near_cells */
    $near_cells = [];
    // brute force procedural aproach, which is super clear
    // up left
    $near_cells[] = matrix_at($matrix, $x - 1, $y - 1);
    // directly above
    $near_cells[] = matrix_at($matrix, $x - 1, $y);
    // up right
    $near_cells[] = matrix_at($matrix, $x - 1, $y + 1);
    // left
    $near_cells[] = matrix_at($matrix, $x, $y - 1);
    // right
    $near_cells[] = matrix_at($matrix, $x, $y + 1);
    // bottom left
    $near_cells[] = matrix_at($matrix, $x + 1, $y - 1);
    // directly below
    $near_cells[] = matrix_at($matrix, $x + 1, $y);
    // bottom right
    $near_cells[] = matrix_at($matrix, $x + 1, $y + 1);
    // mantieni solo le celle vive
    $near_cells_alive = array_filter($near_cells,
        fn($cell) => true === $cell // true retained, false skipped
    );
    $c_alive_near = count($near_cells_alive);
    return $c_alive_near;
}
/**
 * initialize matrix state
 * by providing a $max=1 50% of the grid will be populated, incrising the number the grid will be less populated
 * @return Matrix
 */
function matrix_init_state(int $c_horizontal, int $c_vertical, int $max = 3): array{
    // populate the matrix
    /** @var Matrix $matrix */
    $matrix = [];
    for ($x = 0; $x < $c_horizontal; $x++) {
        for ($y = 0; $y < $c_vertical; $y++) {
            $rnd_is_alive = (bool) (random_int($min = 0, $max) === 1);
            $matrix[$x][$y] = $rnd_is_alive;
        }
    }
    return $matrix;
}
/**
 * compute the next generation
 * @param Matrix $matrix
 * @return Matrix
 */
function matrix_generate_next(array $matrix, int $c_horizontal, int $c_vertical): array{
    $matrix_next = [];
    for ($x = 0; $x < $c_horizontal; $x++) {
        for ($y = 0; $y < $c_vertical; $y++) {
            $c_alive_near = matrix_count_near_alive($matrix, $x, $y);
            $cell_prev = matrix_at($matrix, $x, $y);
            if (null !== $cell_prev) {
                $will_live = cell_will_live($cell_prev, $c_alive_near);
                $matrix_next[$x][$y] = $will_live;
            } else {
                $msg = sprintf('Error: cant find a cell at [%s,%s]', $x, $y);
                throw new \Exception($msg);
            }
        }
    }
    return $matrix_next;
}
/** @param Matrix $matrix  */
function matrix_dump(array $matrix): string{
    $str = '';
    foreach ($matrix as $x => $row) {
        foreach ($row as $y => $is_alive) {
            $str .= (int) ($is_alive);
        }
        $str .= "\n";
    }
    return $str;
}
function main(): void{
    /** @var Matrix  $matrix */
    $matrix = [];
    $c_horizontal = 10;
    $c_vertical = 8;
    $num_cicles = 30;
    $interval_secs = 1;
    $matrix = matrix_init_state($c_horizontal, $c_vertical);
    for ($i = 0; $i < $num_cicles; $i++) {
        grid_clear();
        // computa la prossima generazione, il nuovo stato e rendilo
        $matrix = matrix_generate_next($matrix, $c_horizontal, $c_vertical);
        echo grid_render($matrix);
        // echo sprintf("cycle %s of {$num_cicles} \n", 1 + $i);
        /** @psalm-suppress ArgumentTypeCoercion */
        sleep($interval_secs);
    }
}
main();