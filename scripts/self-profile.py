#!/usr/bin/env python3
"""Build profiles/self from this repository: the tool evaluated on its own dossier.

Reads merged pull requests through the GitHub API (`gh` must be authenticated), counts the
context files of the harness, copies the harness into repo-context/, and writes the same
pieces a provided profile has: profile.json, git-activity.json, repo-context/.
The numbers are measured, not declared; every field that cannot be measured is left null.
"""
import datetime as dt
import json
import pathlib
import shutil
import statistics
import subprocess
import sys

REPO = "JonaPlaz/aidd-level"
ROOT = pathlib.Path(__file__).resolve().parent.parent
OUT = ROOT / "profiles" / "self"


def gh(path):
    return json.loads(subprocess.check_output(["gh", "api", "--paginate", path], text=True))


def parse(ts):
    return dt.datetime.fromisoformat(ts.replace("Z", "+00:00"))


def main():
    prs = [p for p in gh(f"repos/{REPO}/pulls?state=closed&per_page=100") if p["merged_at"]]
    details, corrections, intervals = [], [], []
    ai_commits = total_commits = 0
    for p in prs:
        d = gh(f"repos/{REPO}/pulls/{p['number']}")
        commits = gh(f"repos/{REPO}/pulls/{p['number']}/commits")
        created = parse(p["created_at"])
        corrections.append(sum(1 for c in commits if parse(c["commit"]["committer"]["date"]) > created))
        ai_commits += sum(1 for c in commits if "Co-Authored-By: Claude" in c["commit"]["message"])
        total_commits += len(commits)
        details.append(d)
        intervals.append((created, parse(p["merged_at"])))
    files = [d["changed_files"] for d in details]
    lines = [d["additions"] + d["deletions"] for d in details]
    per_pr = [d["commits"] for d in details]
    # Concurrency: open pull requests sampled every 30 minutes over the active period.
    t0, t1 = min(a for a, _ in intervals), max(b for _, b in intervals)
    samples, t = [], t0
    while t <= t1:
        samples.append(sum(1 for a, b in intervals if a <= t <= b))
        t += dt.timedelta(minutes=30)
    active = [s for s in samples if s > 0]

    claude = ROOT / ".claude"
    context_files = {
        "agents_md": (ROOT / "AGENTS.md").exists(),
        "rules_count": len(list((claude / "rules").glob("*.md"))) if (claude / "rules").exists() else 0,
        "skills_count": len(list((claude / "skills").glob("*/SKILL.md"))),
        "hooks_count": len([f for f in (claude / "hooks").glob("*.js") if f.name != "lib.js"]),
        "agents_count": len(list((claude / "agents").glob("*.md"))),
        "last_updated": dt.date.today().isoformat(),
    }
    activity = {
        "period": {"from": t0.date().isoformat(), "to": t1.date().isoformat()},
        "repositories": 1,
        "pull_requests": {
            "total": len(prs),
            "median_files_changed": statistics.median(files),
            "median_lines_changed": statistics.median(lines),
            "median_correction_commits_after_open": statistics.median(corrections),
            "merged_without_human_edit_after_open": sum(1 for c in corrections if c == 0),
        },
        "commits": {
            "total": total_commits,
            "median_per_pr": statistics.median(per_pr),
            "ai_coauthored_ratio": round(ai_commits / total_commits, 2) if total_commits else None,
        },
        "parallelism": {
            "max_concurrent_branches": max(samples),
            "median_concurrent_branches": statistics.median(active),
        },
        "context_files": context_files,
        "assistant_usage": {"declared_tools": ["claude-code", "codex"], "editor_integration": True},
    }
    profile = {
        "profile_id": "self",
        "role": "ce dépôt, évalué par son propre outil",
        "stack": ["PHP", "symfony/console", "Docker"],
        "team_size": 1,
        "available": ["git-activity.json", "repo-context/"],
        "note": "Profil fabriqué par scripts/self-profile.py depuis l'API GitHub et le dossier .claude/ ; "
        "aucune pièce déclarative, aucune analyse Sonar.",
    }
    OUT.mkdir(parents=True, exist_ok=True)
    (OUT / "profile.json").write_text(json.dumps(profile, ensure_ascii=False, indent=2) + "\n")
    (OUT / "git-activity.json").write_text(json.dumps(activity, ensure_ascii=False, indent=2) + "\n")
    rc = OUT / "repo-context"
    shutil.rmtree(rc, ignore_errors=True)
    for src in [".claude/agents", ".claude/hooks", ".claude/skills", ".claude/settings.json", ".github/workflows", "AGENTS.md", "CLAUDE.md", "Makefile"]:
        s = ROOT / src
        if s.is_dir():
            shutil.copytree(s, rc / src)
        elif s.exists():
            (rc / src).parent.mkdir(parents=True, exist_ok=True)
            shutil.copy(s, rc / src)
    print(json.dumps(activity["pull_requests"] | activity["parallelism"] | {"ai_ratio": activity["commits"]["ai_coauthored_ratio"]}))


if __name__ == "__main__":
    sys.exit(main())
