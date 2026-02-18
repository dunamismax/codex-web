#!/usr/bin/env bun

const command = process.argv[2];

const runChecked = async (cmd: string[]) => {
  const proc = Bun.spawn(cmd, { stdout: "inherit", stderr: "inherit", stdin: "inherit" });
  const code = await proc.exited;
  if (code !== 0) {
    process.exit(code);
  }
};

const main = async () => {
  if (!command) {
    console.error("Usage: bun run scripts/cli.ts <command>");
    process.exit(1);
  }

  switch (command) {
    case "dev": {
      await runChecked(["bun", "run", "dev:server"]);
      break;
    }
    case "build": {
      await runChecked(["bun", "run", "build:app"]);
      break;
    }
    case "lint": {
      await runChecked(["bunx", "@biomejs/biome", "check", "."]);
      break;
    }
    case "typecheck": {
      await runChecked(["bunx", "tsc", "--noEmit"]);
      break;
    }
    case "format": {
      await runChecked(["bunx", "@biomejs/biome", "check", "--write", "."]);
      break;
    }
    case "scry:doctor": {
      await runChecked(["bun", "run", "lint"]);
      await runChecked(["bun", "run", "typecheck"]);
      await runChecked(["bun", "run", "build"]);
      console.log("doctor: ok");
      break;
    }
    case "scry:bootstrap": {
      await runChecked(["bun", "install"]);
      console.log("bootstrap: dependencies installed");
      break;
    }
    case "scry:setup:workstation":
    case "scry:setup:ssh_backup":
    case "scry:setup:ssh_restore":
    case "scry:projects:list":
    case "scry:projects:doctor":
    case "scry:projects:install":
    case "scry:projects:verify": {
      console.log(`${command}: not implemented in this repo yet`);
      break;
    }
    default:
      console.error(`Unknown command: ${command}`);
      process.exit(1);
  }
};

void main();
