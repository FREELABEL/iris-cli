# IRIS Code Installation Script

IRIS Code is an AI-powered coding assistant that runs in your terminal. This document explains how the installation script works.

## Quick Install

```bash
curl -fsSL https://heyiris.io/install-code | bash && ~/.iris/bin/iris
```

This command:
1. Downloads and runs the install script
2. Launches IRIS Code in your **current directory**

---

## How It Works

### Installation Location vs Working Directory

Understanding the difference between these two concepts is important:

| Concept | Path | Description |
|---------|------|-------------|
| **Binary Location** | `~/.iris/bin/iris` | Where the IRIS Code executable is installed (always in your home directory) |
| **Working Directory** | Current directory | Where IRIS Code operates when you run it |

### Example

If you're in `/projects/my-app` and run:

```bash
~/.iris/bin/iris
```

- The binary executes from `~/.iris/bin/iris` (home directory)
- IRIS Code operates in `/projects/my-app` (your current directory)

This is similar to how other CLI tools work (e.g., `/usr/bin/code` for VS Code) - the binary location doesn't affect your working directory.

---

## Install Script Details

### What the Script Does

1. **Detects your system**: OS (macOS, Linux, Windows) and architecture (arm64, x64)
2. **Downloads the correct binary**: From GitHub releases at `github.com/FREELABEL/iris-opencode`
3. **Installs to `~/.iris/bin/`**: Creates the directory if needed
4. **Updates your PATH**: Adds `~/.iris/bin` to your shell config (`.zshrc`, `.bashrc`, etc.)
5. **Shows completion message**: Instructions for launching IRIS Code

### Supported Platforms

| Platform | Architecture | Archive |
|----------|--------------|---------|
| macOS | arm64 (Apple Silicon) | `.zip` |
| macOS | x64 (Intel) | `.zip` |
| Linux | arm64 | `.tar.gz` |
| Linux | x64 | `.tar.gz` |
| Windows | x64 | `.zip` |

### Script Options

```bash
# Show help
curl -fsSL https://heyiris.io/install-code | bash -s -- --help

# Install specific version
curl -fsSL https://heyiris.io/install-code | bash -s -- --version 1.1.6

# Install from local binary
./install --binary /path/to/iris

# Don't modify shell config
curl -fsSL https://heyiris.io/install-code | bash -s -- --no-modify-path
```

---

## Why Two Commands?

The install command uses `&&` to chain two separate commands:

```bash
curl -fsSL https://heyiris.io/install-code | bash && ~/.iris/bin/iris
```

**Why not auto-launch from the script?**

When you pipe a script from curl (`curl ... | bash`), stdin is consumed by the pipe. IRIS Code's TUI (Text User Interface) requires a proper terminal (TTY) for:
- Keyboard input
- Mouse tracking
- Interactive prompts

By using `&&`, the second command (`~/.iris/bin/iris`) runs with a fresh stdin connected to your terminal, ensuring the TUI works correctly.

---

## After Installation

Once installed, you can run IRIS Code from any directory:

```bash
# Using the full path
~/.iris/bin/iris

# Or just (if PATH is updated)
iris
```

IRIS Code will start in whatever directory you run it from - perfect for working on different projects.

---

## Troubleshooting

### "command not found: iris"

Your PATH hasn't been updated yet. Either:
1. Open a new terminal window
2. Source your shell config: `source ~/.zshrc` (or `.bashrc`)
3. Use the full path: `~/.iris/bin/iris`

### TUI Not Working (escape codes, no input)

This happens if you try to launch directly from the piped script. Use the two-command approach:

```bash
# Correct
curl -fsSL https://heyiris.io/install-code | bash && ~/.iris/bin/iris

# Incorrect (TUI will be broken)
curl -fsSL https://heyiris.io/install-code | bash -c "~/.iris/bin/iris"
```

### Permission Denied

The script should set permissions automatically, but if needed:

```bash
chmod 755 ~/.iris/bin/iris
```

---

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `VERSION` | Install specific version | Latest |
| `INSTALL_DIR` | Installation directory | `~/.iris/bin` |

Example:
```bash
VERSION=1.1.6 curl -fsSL https://heyiris.io/install-code | bash
```

---

## Uninstalling

To remove IRIS Code:

```bash
# Remove the binary
rm -rf ~/.iris

# Remove PATH entry from shell config (manual)
# Edit ~/.zshrc or ~/.bashrc and remove the iris-code line
```

---

## Source Code

- **Install Script**: [heyiris.io/install-code](https://heyiris.io/install-code)
- **GitHub Repository**: [github.com/FREELABEL/iris-opencode](https://github.com/FREELABEL/iris-opencode)
- **Releases**: [github.com/FREELABEL/iris-opencode/releases](https://github.com/FREELABEL/iris-opencode/releases)
