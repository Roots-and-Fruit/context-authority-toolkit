#!/usr/bin/env node
/**
 * Extract a Keep a Changelog 2.0 version section, or slice it into readme.txt.
 *
 * GitHub Actions copies this file into each plugin at
 * .github/scripts/extract-release-notes.mjs. Keep those copies identical
 * to the skill's scripts/ folder.
 */

import fs from "node:fs";
import path from "node:path";
import process from "node:process";

const CHANGELOG_HEADING =
  /^## \[([^\]]+)\](?:\s+-\s+(\d{4}-\d{2}-\d{2}))?\s*$/;
const README_VERSION = /^=\s+(\S+)\s+=\s*$/;
const README_SECTION = /^==\s+.+\s+==\s*$/;

function usage(exitCode) {
  const text = `Usage:
  extract-release-notes.mjs --version <x.y.z> [--out <file>] [--changelog <path>] [--readme <path>]
  extract-release-notes.mjs --slice-readme [--changelog <path>] [--readme <path>]

Looks for CHANGELOG.md in the working directory. If that file is missing,
--version falls back to readme.txt. If CHANGELOG.md exists but the version
heading is missing, the command fails (it does not fall back).
`;
  process.stderr.write(text);
  process.exit(exitCode);
}

function parseArgs(argv) {
  const out = {
    version: "",
    out: "",
    changelog: "",
    readme: "",
    sliceReadme: false,
    help: false,
  };
  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i];
    const next = argv[i + 1];
    if (arg === "--help" || arg === "-h") {
      out.help = true;
    } else if (arg === "--slice-readme") {
      out.sliceReadme = true;
    } else if (arg === "--version") {
      out.version = next || "";
      i += 1;
    } else if (arg === "--out") {
      out.out = next || "";
      i += 1;
    } else if (arg === "--changelog") {
      out.changelog = next || "";
      i += 1;
    } else if (arg === "--readme") {
      out.readme = next || "";
      i += 1;
    } else {
      process.stderr.write(`Unknown argument: ${arg}\n`);
      usage(1);
    }
  }
  return out;
}

function readFile(filePath) {
  return fs.readFileSync(filePath, "utf8").replace(/\r\n/g, "\n").replace(/\r/g, "\n");
}

function trimBody(text) {
  return text.replace(/^\n+/, "").replace(/\n+$/, "");
}

function splitChangelog(markdown) {
  const lines = markdown.split("\n");
  const sections = [];
  let current = null;
  const footer = [];
  let inFooter = false;

  for (const line of lines) {
    if (/^\[[^\]]+\]:\s+\S+/.test(line)) {
      inFooter = true;
    }
    if (inFooter) {
      footer.push(line);
      continue;
    }
    const match = line.match(CHANGELOG_HEADING);
    if (match) {
      if (current) {
        sections.push(current);
      }
      current = {
        id: match[1],
        date: match[2] || "",
        heading: line,
        body: [],
      };
      continue;
    }
    if (current) {
      current.body.push(line);
    }
  }
  if (current) {
    sections.push(current);
  }
  return { sections, footer: footer.join("\n") };
}

function compareUrl(footer, version) {
  const escaped = version.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const match = footer.match(new RegExp(`^\\[${escaped}\\]:\\s+(\\S+)`, "m"));
  return match ? match[1] : "";
}

function extractChangelogSection(markdown, version) {
  const { sections, footer } = splitChangelog(markdown);
  const found = sections.find((section) => section.id === version);
  if (!found) {
    return null;
  }
  const body = trimBody(found.body.join("\n"));
  const url = compareUrl(footer, version);
  const parts = [];
  if (found.date) {
    parts.push(`Released ${found.date}.`);
    parts.push("");
  }
  if (body) {
    parts.push(body);
  }
  if (url) {
    if (parts.length) {
      parts.push("");
    }
    parts.push(`[Full changelog](${url})`);
  }
  return trimBody(parts.join("\n"));
}

function extractReadmeSection(readme, version) {
  const lines = readme.split("\n");
  const body = [];
  let found = false;
  for (const line of lines) {
    const trimmed = line.trim();
    if (README_SECTION.test(trimmed)) {
      if (found) {
        break;
      }
      continue;
    }
    const versionMatch = trimmed.match(README_VERSION);
    if (versionMatch) {
      if (found) {
        break;
      }
      if (versionMatch[1] === version) {
        found = true;
      }
      continue;
    }
    if (found) {
      body.push(line.replace(/^\*\s+/, "- "));
    }
  }
  if (!found) {
    return null;
  }
  const text = trimBody(body.join("\n"));
  if (!text) {
    return "";
  }
  return `## Changes in v${version}\n\n${text}`;
}

function changelogTypeToReadme(line) {
  const match = line.match(/^###\s+(Added|Changed|Deprecated|Removed|Fixed|Security)\s*$/);
  if (match) {
    return `**${match[1]}**`;
  }
  if (line.startsWith("- ")) {
    return `* ${line.slice(2)}`;
  }
  return line;
}

function sliceReadme(changelogMarkdown, readme) {
  const { sections } = splitChangelog(changelogMarkdown);
  const shipped = sections.filter((section) => section.id !== "Unreleased");
  if (!shipped.length) {
    throw new Error("CHANGELOG.md has no shipped versions to slice into readme.txt.");
  }

  const chunks = ["== Changelog ==", ""];
  for (const section of shipped) {
    chunks.push(`= ${section.id} =`);
    if (section.date) {
      chunks.push(`Released ${section.date}.`);
      chunks.push("");
    }
    const bodyLines = trimBody(section.body.join("\n")).split("\n");
    for (const line of bodyLines) {
      chunks.push(changelogTypeToReadme(line));
    }
    chunks.push("");
  }

  const sliced = `${trimBody(chunks.join("\n"))}\n`;
  const lines = readme.split("\n");
  const start = lines.findIndex((line) => line.trim() === "== Changelog ==");
  if (start === -1) {
    throw new Error("readme.txt has no == Changelog == section.");
  }
  let end = lines.length;
  for (let i = start + 1; i < lines.length; i += 1) {
    if (README_SECTION.test(lines[i].trim())) {
      end = i;
      break;
    }
  }
  const before = lines.slice(0, start).join("\n").replace(/\n+$/, "");
  const after = lines.slice(end).join("\n").replace(/^\n+/, "");
  const parts = [before, sliced];
  if (after) {
    parts.push(after);
  }
  return `${parts.join("\n\n").replace(/\n{3,}/g, "\n\n").replace(/\n+$/, "")}\n`;
}

function resolvePath(cwd, explicit, basename) {
  if (explicit) {
    return path.resolve(cwd, explicit);
  }
  return path.join(cwd, basename);
}

function main() {
  const args = parseArgs(process.argv.slice(2));
  if (args.help) {
    usage(0);
  }
  if (!args.sliceReadme && !args.version) {
    usage(1);
  }

  const cwd = process.cwd();
  const changelogPath = resolvePath(cwd, args.changelog, "CHANGELOG.md");
  const readmePath = resolvePath(cwd, args.readme, "readme.txt");
  const hasChangelog = fs.existsSync(changelogPath);

  if (args.sliceReadme) {
    if (!hasChangelog) {
      process.stderr.write(`Missing ${changelogPath}\n`);
      process.exit(1);
    }
    if (!fs.existsSync(readmePath)) {
      process.stderr.write(`Missing ${readmePath}\n`);
      process.exit(1);
    }
    const next = sliceReadme(readFile(changelogPath), readFile(readmePath));
    fs.writeFileSync(readmePath, next, "utf8");
    process.stdout.write(`Updated ${readmePath}\n`);
    return;
  }

  let notes = "";
  if (hasChangelog) {
    notes = extractChangelogSection(readFile(changelogPath), args.version);
    if (notes === null) {
      process.stderr.write(
        `CHANGELOG.md has no ## [${args.version}] heading.\n`
      );
      process.exit(1);
    }
  } else if (fs.existsSync(readmePath)) {
    notes = extractReadmeSection(readFile(readmePath), args.version);
    if (notes === null) {
      process.stderr.write(
        `readme.txt has no changelog entries for ${args.version}.\n`
      );
      process.exit(1);
    }
  } else {
    process.stderr.write("Neither CHANGELOG.md nor readme.txt was found.\n");
    process.exit(1);
  }

  if (!/\S/.test(notes)) {
    process.stderr.write(`No changelog body for ${args.version}.\n`);
    process.exit(1);
  }

  notes = `${trimBody(notes)}\n`;
  if (args.out) {
    fs.writeFileSync(path.resolve(cwd, args.out), notes, "utf8");
  } else {
    process.stdout.write(notes);
  }
}

main();
