package main
/*
program description:
    game of life implemented in GO
compilation:
    go build main.go
*/
import (
    "fmt"
    "math/rand"
    "time"
    // "os"
    // "os/exec"
    // "log"
)

// makes a random bool
func RandBool(max int) bool {
    rand.Seed(time.Now().UnixNano())
    return rand.Intn(max) == 1
}

// make a matrix and init to rand values
func matrixMake(h int, w int) [][]bool {
    // make a grid
    matrix := make([][]bool, h)
    for i := range matrix {
        matrix[i] = make([]bool, w)
    }
    // init
    for i := range matrix {
        for j := range matrix[i] {
            matrix[i][j] = RandBool(3) // with bigger max you get less live cells
        }
    }
    return matrix
}

// access to the matrix
func matrixAt(matrix [][]bool, x int, y int) bool {
    if x >= 0 && y >= 0 && len(matrix) > x && len(matrix[x]) > y {
        return matrix[x][y]
    }
    return false
}
func matrixCountNearAlive(matrix [][]bool, x int, y int) int {
    // brute force procedural aproach, which is super clear
    var a_cells [8]bool
    // up left
    a_cells[0] = matrixAt(matrix, x-1, y-1)
    // directly above
    a_cells[1] = matrixAt(matrix, x-1, y)
    // up right
    a_cells[2] = matrixAt(matrix, x-1, y+1)
    // left
    a_cells[3] = matrixAt(matrix, x, y-1)
    // right
    a_cells[4] = matrixAt(matrix, x, y+1)
    // bottom left
    a_cells[5] = matrixAt(matrix, x+1, y-1)
    // directly below
    a_cells[6] = matrixAt(matrix, x+1, y)
    // bottom right
    a_cells[7] = matrixAt(matrix, x+1, y+1)
    // count alive cells
    ok_cells := 0
    for i := 0; i < len(a_cells); i++ {
        if a_cells[i] == true {
            ok_cells++
        }
    }
    return ok_cells
}
func cellWillLive(alive bool, num_alive int) bool {
    // Any live cell with two or three live neighbours survives to the next generation.
    survives := alive && (num_alive == 2 || num_alive == 3)
    // Any dead cell with exactly three live neighbours becomes a live cell, as if by reproduction.
    reproduces := !alive && num_alive == 3
    if survives || reproduces {
        return true
    }
    // Death is the most obvious outcome
    return false
}

// recompute state
func matrixNewGen(matrix [][]bool) [][]bool {
    matrix2 := make([][]bool, len(matrix)) // copia della stessa grandezza
    for i := range matrix {
        matrix2[i] = make([]bool, len(matrix[0]))
        for j := range matrix[i] {
            num_alive := matrixCountNearAlive(matrix, i, j)
            alive := matrix[i][j]
            will_live := cellWillLive(alive, num_alive)
            // fmt.Printf("cellWillLive %v+%d = %v \n", alive, num_alive, will_live)
            matrix2[i][j] = will_live
        }
    }
    return matrix2
}

//------------------------------------------------------------------------------
// render the matrix into a grid
func gridRender(matrix [][]bool) string {
    str := ""
    cell := ""
    for _, row := range matrix {
        for _, is_alive := range row {
            cell = cellRender(is_alive)
            str += cell
        }
        str += "\n"
    }
    return str
}
func gridClear() {
    // cmd := exec.Command("clear") //Linux example, its tested
    // cmd.Stdout = os.Stdout
    // err := cmd.Run()
    // if err != nil {
    //     fmt.Printf("cmd.Run() failed with %s\n", err)
    // }
    // fmt.Print("\033[H\033[2J")
}
func cellRender(is_alive bool) string {
    if is_alive {
        return "#"
    } else {
        return "."
    }
}
func main() {
    h := 10
    w := 20
    matrix := matrixMake(h, w)
    for i := 0; i < 300; i++ {
        gridClear()
        matrix = matrixNewGen(matrix)
        // render and print the state
        s_grid := gridRender(matrix)
        fmt.Print(s_grid)
        fmt.Printf("generation %d of 300 \n", i)
        time.Sleep(time.Second)
    }
}
