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

func workingDir() string {
	dir, e := os.Getwd()

	if e == nil {
		return dir
	} else {
		panic("Panicking because I could not determine the current working directory.")
	}
}

func execPhp(arguments ...string) *exec.Cmd {
	bin := path.Join(workingDir(), "bin", "php")

	if runtime.GOARCH == "amd64" {
		bin += "-x86_64"
	} else {
		bin += "-arm64"
	}

	if runtime.GOOS == "windows" {
		bin += ".exe"
	}
	return exec.Command(bin, arguments...)
}

func execComposer(arguments ...string) *exec.Cmd {
	bin := path.Join(workingDir(), "bin", "composer")

	phpArguments := append([]string{bin}, arguments...)

	return execPhp(phpArguments...)
}

func webRoot(projectRoot string) (string, error) {
	output, e := execComposer("config", "extra.drupal-scaffold.locations.web-root", "--working-dir="+projectRoot).Output()

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
		e = errors.New("Cannot figure out how to open a browser on this operating system.")
	}

	if e != nil {
		fmt.Println("Could not figure out how to open a browser. Visit " + url + " to get started.")
		return
	}

	env := os.Environ()
	binName := path.Base(bin)

	if runtime.GOOS == "windows" {
		syscall.Exec(bin, []string{binName, "\"web\"", "\"" + url + "\""}, env)
	} else {
		syscall.Exec(bin, []string{binName, url}, env)
	}
}

func main() {
	// @todo Make these configurable by an easily parsed config file
	const template string = "drupal/recommended-project"
	const projectDir string = "drupal"

	projectRoot := path.Join(workingDir(), projectDir)

	_, e := os.Stat(projectRoot)
	if e != nil && os.IsNotExist(e) {
		cmd := execComposer("create-project", template, projectDir)
		cmd.Stdout = os.Stdout
		cmd.Stderr = os.Stderr
		cmd.Run()
	}

	webRoot, e := webRoot(projectRoot)

	if e != nil {
		panic("Panicking because I could not determine the web root of the project.")
	}

	port, e := findAvailablePort()
	if e != nil {
		fmt.Println(e)
		return
	}

	url := "localhost:" + strconv.Itoa(port)

	server := execPhp("-S", url, ".ht.router.php")
	server.Dir = path.Join(projectRoot, webRoot)
	e = server.Start()

	if e == nil {
		fmt.Println("The built-in PHP web server is running on port", port)
	} else {
		fmt.Println(e)
	}

	time.Sleep(2 * time.Second)
	openBrowser("http://" + url)
}
