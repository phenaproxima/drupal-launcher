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

func main() {
	var workingDir string
    // Any errors we encounter along the way will go into this variable.
	var e error

	me, e := os.Executable()
	if e == nil {
	    workingDir = path.Dir(me)
    } else {
        // We can't continue if we don't know the path to the running executable.
	    panic(e)
	}

    // Figure out the full paths of the PHP interpreter, Composer, and
    // the project root.
	phpBin := path.Join(workingDir, "bin", "php")
	if runtime.GOOS == "windows" {
		phpBin += ".exe"
	}
	composerBin := path.Join(workingDir, "bin", "composer")
	projectRoot := path.Join(workingDir, "drupal")

    // If the Drupal code base isn't already there, use Composer to set it up.
	_, e = os.Stat(projectRoot)
	if e != nil && os.IsNotExist(e) {
        fmt.Println("Initializing the project...")

		e = exec.Command(composerBin, "create-project", "drupal/recommended-project", path.Base(projectRoot), "--no-install").Run()
		// If we couldn't create the project, we're screwed.
		if e != nil {
            panic(e)
		}
	}

	lockFile := path.Join(projectRoot, "composer.lock")
	_, e = os.Stat(lockFile)
	if e != nil && os.IsNotExist(e) {
        fmt.Println("Installing dependencies. This may take a few minutes, but only needs to be done once.")

        e = exec.Command(composerBin, "install", "--working-dir=" + projectRoot).Run()
        // If we couldn't install dependencies, we're screwed.
        if e != nil {
            panic(e)
        }
	}

	var port int
	port, e = findAvailablePort()
	if e != nil {
        // Can't continue if we can't find an open port.
        panic(e)
	}

	url := "localhost:" + strconv.Itoa(port)

    // Prepare to start the built-in PHP web server, which is apparently spawned
    // into a separate process that can outlive this one.
	server := exec.Command(phpBin, "-S", url, ".ht.router.php")

	// The server needs to be run in the web root, so ask Composer where it is.
	var output []byte
	output, e = exec.Command(composerBin, "config", "extra.drupal-scaffold.locations.web-root", "--working-dir=" + projectRoot).Output()
	if e == nil {
		webRoot := string(output)
		webRoot = strings.TrimSpace(webRoot)
		webRoot = strings.TrimRight(webRoot, "/")

		server.Dir = path.Join(projectRoot, webRoot)
	} else {
        fmt.Println("Could not figure out the web root, so I'm assuming it's the same as the project root.")
        server.Dir = projectRoot
	}

	e = server.Start()
	if e != nil {
        // If we couldn't start the server, we're screwed.
	    panic(e)
	}

    // Give the server a couple of seconds to start up.
	time.Sleep(2 * time.Second)

	url = "http://" + url

    // Figure out which utility we should use to open a browser. This varies by
    // operating system. If we can't figure it out, just print the URL and exit.
    var openerBin string
	if runtime.GOOS == "windows" {
		openerBin, e = exec.LookPath("start")
	} else if runtime.GOOS == "linux" {
		openerBin, e = exec.LookPath("xdg-open")
	} else if runtime.GOOS == "darwin" {
		openerBin, e = exec.LookPath("open")
	} else {
        e = errors.New("Unsupported operating system.")
	}

	if e != nil {
		fmt.Println("Drupal is up and running! Visit", url, "to get started.")
	    return
	}

	env := os.Environ()
	openerName := path.Base(openerBin)
    // According to https://gobyexample.com/execing-processes, this will terminate
    // the launcher and transfer control to the opener utility. Perfect -- we're
    // done anyway.
	if runtime.GOOS == "windows" {
		syscall.Exec(openerBin, []string{ openerName, "\"web\"", "\"" + url + "\"" }, env)
	} else {
		syscall.Exec(openerBin, []string{ openerName, url }, env)
	}
}
