package main

import (
	"errors"
	"fmt"
	"net"
	"os"
	"os/exec"
	"path"
	"runtime"
	"strconv"
	"strings"
	"syscall"
	"time"
)

// I know, global variables are bad. But in this case, it makes sense.
// We'll set it at the top of main() and not change it.
var workingDir string

func execPhp(arguments ...string) *exec.Cmd {
	bin := path.Join(workingDir, "bin", "php")

	if runtime.GOOS == "linux" {
		if runtime.GOARCH == "amd64" {
			bin += "-x86_64"
		} else {
			bin += "-aarch64"
		}
	} else if runtime.GOOS == "windows" {
		bin += ".exe"
	}

	return exec.Command(bin, arguments...)
}

func execComposer(arguments ...string) *exec.Cmd {
	bin := path.Join(workingDir, "bin", "composer")

	phpArguments := append([]string{ bin }, arguments...)

	return execPhp(phpArguments...)
}

func getWebRoot(projectRoot string) (string, error) {
	output, e := execComposer("config", "extra.drupal-scaffold.locations.web-root", "--working-dir=" + projectRoot).Output()

	if e == nil {
		webRoot := string(output)
		webRoot = strings.TrimSpace(webRoot)
		webRoot = strings.TrimRight(webRoot, "/")

		return webRoot, nil
	} else {
		return ".", e
	}
}

func findAvailablePort() (int, error) {
	var portString string

	for port := 8888; port < 10000; port++ {
		portString = ":" + strconv.Itoa(port)

		socket, e := net.Listen("tcp", portString)

		if e == nil {
			if socket.Close() == nil {
				return port, e
			}
			return -1, errors.New("Port " + portString + " was open, but could not be closed.")
		}
	}
	return -1, errors.New("Could not find an open port.")
}

func openBrowser(url string) {
	var bin string
	var e error

	if runtime.GOOS == "windows" {
		bin, e = exec.LookPath("start")
	} else if runtime.GOOS == "linux" {
		bin, e = exec.LookPath("xdg-open")
	} else if runtime.GOOS == "darwin" {
		bin, e = exec.LookPath("open")
	} else {
		e = errors.New("Could not figure out how to open a browser. Visit " + url + " to get started.")
	}

	if e != nil {
        fmt.Println(e)
	    return
	}

	env := os.Environ()
	binName := path.Base(bin)

	if runtime.GOOS == "windows" {
		syscall.Exec(bin, []string{ binName, "\"web\"", "\"" + url + "\"" }, env)
	} else {
		syscall.Exec(bin, []string{ binName, url }, env)
	}
}

func main() {
    // Any errors we encounter along the way will go into this variable.
	var e error

    // Set this global variable once. I know Globals Are Bad but eh...it
    // makes sense in this case.
	workingDir, e = os.Getwd()
	if e != nil {
	    panic("Panicking because I could not figure out the current working directory.")
	}

	projectRoot := path.Join(workingDir, "drupal")

    // If the Drupal code base isn't already there, use Composer to install it.
	_, e = os.Stat(projectRoot)
	if e != nil && os.IsNotExist(e) {
        fmt.Println("Installing dependencies. This may take a few minutes, but only needs to be done once.")
		execComposer("create-project", "drupal/cms:^1-rc2", "--stability=rc", path.Base(projectRoot)).Run()
	}

	var port int
	port, e = findAvailablePort()
	if e != nil {
        // Can't continue if we can't find an open port.
        panic(e)
	}

	url := "localhost:" + strconv.Itoa(port)

    // Start the built-in PHP web server, which is apparently spawned into a
    // separate process that can outlive this one.
	server := execPhp("-S", url, ".ht.router.php")

	// The server needs to be run in the web root.
	var webRoot string
	webRoot, e = getWebRoot(projectRoot)
	if e == nil {
	    server.Dir = path.Join(projectRoot, webRoot)
	}
	if e != nil {
        fmt.Println("Could not figure out the web root, so I'm assuming it's the same as the project root.")
        server.Dir = projectRoot
	}

	e = server.Start()
	if e == nil {
		fmt.Println("The built-in PHP web server is running on port", port)
	} else {
        // Couldn't start the server, so we're sorta screwed.
	    panic(e)
	}

    // Give the server a couple of seconds to start up.
	time.Sleep(2 * time.Second)
	openBrowser("http://" + url)
}
