# CI/CD Setup Guide — Student Task Tracker

A step-by-step guide to satisfy every requirement in the project brief, tailored to this project's stack: **Vue 3 frontend + PHP 8 API + MySQL**, hosted with **GitLab (self-hosted on DigitalOcean)**, **SonarQube**, and **Jira Cloud**.

Two config files have already been created for you at the repository root:

- `.gitlab-ci.yml` — the CI/CD pipeline (build → test → SonarQube → simulated deploy).
- `sonar-project.properties` — tells SonarQube what to analyze.

Work through the sections in order. Each maps directly to a requirement (2.1–2.9) and to the assessment criteria.

---

## 0. How the pieces fit together

```
Your PC ──push──> GitLab (self-hosted on a DigitalOcean Droplet)
                     │
                     ├── runs the CI/CD pipeline (.gitlab-ci.yml) on a GitLab Runner
                     │        build → test → SonarQube scan → deploy (simulated)
                     │
                     ├── sends code to SonarQube (also on the Droplet) for quality analysis
                     │
                     └── integrates with Jira Cloud for issue tracking + automation
```

One DigitalOcean Droplet hosts **GitLab**, the **GitLab Runner**, and **SonarQube**. Jira Cloud is a hosted SaaS (no server needed). Your PC only pushes code.

---

## 1. Explain CI/CD (Requirement 2.1)

Use this in your video intro.

**Continuous Integration (CI)** is the practice of merging code changes frequently into a shared repository, where each change automatically triggers a build and a set of tests. It catches integration bugs early instead of at the end.

**Continuous Delivery/Deployment (CD)** extends CI: once the code passes all checks, it is automatically prepared for release (delivery) or pushed all the way to production (deployment).

For this project, the pipeline automates four things every time you push:

1. **Build** — compile the Vue frontend into static files.
2. **Test** — lint the frontend (ESLint) and check every PHP file for syntax errors.
3. **Quality analysis** — SonarQube inspects the code for bugs, vulnerabilities, and code smells.
4. **Deploy** — a simulated deployment step (prints what a real release would do).

The benefit: every change is automatically validated for correctness and quality before it ships, so the team ships faster with fewer defects.

---

## 2. Set up DigitalOcean (Requirement 2.2)

1. Create an account at digitalocean.com.
2. Create a **Droplet**:
   - **Image:** Ubuntu 22.04 (LTS).
   - **Size:** GitLab + SonarQube are memory-hungry. Choose at least **8 GB RAM / 4 vCPUs** (GitLab alone recommends ~8 GB; SonarQube needs ~2–3 GB more). A smaller Droplet will crash or run extremely slowly.
   - **Authentication:** choose **SSH key** (more secure than password — see the next section to create one).
   - **Region:** pick the one closest to you.
3. Note the Droplet's **public IP address** — you'll use it everywhere below.

> **Cost reminder:** an 8 GB Droplet costs money per hour it exists. Turn it on only when working, and **destroy it** (not just power off — powered-off Droplets still bill) once you've recorded your video. Take screenshots/recordings as you go so you don't have to keep it running.

---

## 3. Access the Droplet with SSH (Requirement 2.3)

**What SSH is:** Secure Shell — an encrypted protocol for logging into and running commands on a remote server over the internet. It replaces insecure plain-text logins and is the standard way to administer cloud servers.

**Create a key pair (on your PC, in PowerShell):**

```powershell
ssh-keygen -t ed25519 -C "your-email@example.com"
```

This creates a private key (keep secret) and a public key (`.pub`). Paste the **public** key into DigitalOcean when creating the Droplet (or add it under Settings → Security → SSH Keys).

**Connect:**

```bash
ssh root@YOUR_DROPLET_IP
```

For the video: show the terminal connecting and landing in the server shell. **Blur or hide your private key and IP if you prefer** (see Security, section 11).

---

## 4. Self-host GitLab on the Droplet (Requirement 2.4)

SSH into the Droplet, then install GitLab CE (Community Edition):

```bash
# Update and install dependencies
sudo apt-get update
sudo apt-get install -y curl openssh-server ca-certificates tzdata perl

# Add the GitLab package repository
curl https://packages.gitlab.com/install/repositories/gitlab/gitlab-ce/script.deb.sh | sudo bash

# Install GitLab, pointing it at your server's IP or domain
sudo EXTERNAL_URL="http://YOUR_DROPLET_IP" apt-get install -y gitlab-ce
```

After install finishes (a few minutes), get the initial root password:

```bash
sudo cat /etc/gitlab/initial_root_password
```

Open `http://YOUR_DROPLET_IP` in a browser, log in as `root` with that password, and change it.

**Install a GitLab Runner** (this actually executes your pipeline jobs). On the same Droplet:

```bash
curl -L "https://packages.gitlab.com/install/repositories/runner/gitlab-runner/script.deb.sh" | sudo bash
sudo apt-get install -y gitlab-runner
```

Then register it with the **Docker executor** (your pipeline uses Docker images like `node:18`, `php:8.2-cli`):

```bash
sudo gitlab-runner register
```

When prompted:
- **URL:** `http://YOUR_DROPLET_IP`
- **Token:** from GitLab → your project → Settings → CI/CD → Runners.
- **Executor:** `docker`
- **Default image:** `alpine:latest`

(You'll need Docker installed on the Droplet: `sudo apt-get install -y docker.io`.)

---

## 5. Create a GitLab repository and push your code (Requirement 2.5)

1. In your self-hosted GitLab, click **New project → Create blank project**, name it `student-task-tracker`.
2. On your PC, point your existing repo at the new GitLab remote and push. Your repo already has git history, so:

```bash
cd "C:\Users\User\OneDrive\Documents\SOFTWARE CONSTRUCTION\SoftwareConstruction-Task_Tracker"
git remote add gitlab http://YOUR_DROPLET_IP/root/student-task-tracker.git
git add .gitlab-ci.yml sonar-project.properties CICD_Setup_Guide.md
git commit -m "Add CI/CD pipeline, SonarQube config, and setup guide"
git push gitlab main
```

**Important — do not commit secrets.** Your `student-task-tracker/api/.env` holds your database and Gmail SMTP passwords. It is already git-ignored, so it will not be pushed. Double-check with `git status` that `.env` does not appear. (More in Security, section 11.)

The moment you push, GitLab detects `.gitlab-ci.yml` and runs the pipeline. Watch it under **Build → Pipelines**.

---

## 6. Set up SonarQube and run analysis (Requirement 2.6)

Easiest route: run SonarQube in Docker on the Droplet.

```bash
# SonarQube needs this kernel setting or it won't start
sudo sysctl -w vm.max_map_count=262144

# Run SonarQube (community edition)
sudo docker run -d --name sonarqube -p 9000:9000 sonarqube:lts-community
```

1. Open `http://YOUR_DROPLET_IP:9000`, log in (`admin` / `admin`), change the password.
2. Create a project **manually** with project key `student-task-tracker` (must match `sonar.projectKey` in `sonar-project.properties`).
3. Generate an **analysis token**: My Account → Security → Generate Token. Copy it.
4. In GitLab → your project → **Settings → CI/CD → Variables**, add:
   - `SONAR_HOST_URL` = `http://YOUR_DROPLET_IP:9000`
   - `SONAR_TOKEN` = the token (tick **Masked** so it's hidden in logs).

Now the `sonarqube-check` job in your pipeline will run the scanner automatically. In the SonarQube dashboard you'll see **bugs, vulnerabilities, code smells, and maintainability** ratings — show these results in your video.

---

## 7. Configure the GitLab CI/CD pipeline (Requirement 2.7)

This is already done for you in `.gitlab-ci.yml`. It defines four stages:

1. **build** — `build_frontend`: runs `npm ci` and `npm run build` inside `student-task-tracker/`, producing the `dist/` folder (saved as a pipeline artifact).
2. **test** — two jobs run in parallel:
   - `lint_frontend`: `npm run lint` (ESLint) on the Vue code.
   - `lint_php`: `php -l` syntax check on every PHP file in `api/` (skipping `vendor/`).
3. **sonarqube-check** — sends the code to SonarQube using the variables from section 6.
4. **deploy** — `deploy_simulated`: prints the steps a real deployment would take (safe, no live server needed, satisfies the rubric).

For the video: push a commit, open **Build → Pipelines**, and show all four stages going green. Click into a job to show its log output.

> **Later, if you want a real deploy:** replace `deploy_simulated` with a job that uses an SSH private key (stored as a masked CI variable) to `scp` the `dist/` folder and `api/` to a web server and reload it.

---

## 8. Connect Jira Cloud with GitLab (Requirement 2.8)

1. Create a free **Jira Cloud** site at atlassian.com and a project (e.g., key `STT`).
2. In GitLab → your project → **Settings → Integrations → Jira**, enter your Jira site URL, email, and an Atlassian API token. Enable the integration.
   - For self-hosted GitLab ↔ Jira Cloud, you can also install the official **GitLab for Jira Cloud** app from the Atlassian Marketplace and connect it to your GitLab namespace.
3. Test the link: this connects GitLab commits/branches/merge requests to Jira issues.

---

## 9. Automate Jira ↔ GitLab issue tracking (Requirement 2.9)

The automation works through **commit messages and branch names that reference a Jira issue key**.

1. In Jira, create an issue — note its key, e.g. `STT-1`.
2. On your PC, make a change and reference the key in the commit message:

```bash
git commit -m "STT-1 Fix task list crash when API returns non-array"
git push gitlab main
```

3. Because the Jira integration is on, GitLab links this commit to issue `STT-1` automatically — the commit appears in the Jira issue's **Development** panel.
4. Demonstrate a **Smart Commit** to move the issue automatically. For example:

```bash
git commit -m "STT-1 #comment Fixed the guard #time 30m #close"
```

`#comment` adds a comment, `#time` logs work, and a transition command can move the issue to Done — all triggered from GitLab activity. Show the Jira issue updating live in your video.

---

## 10. Video plan (Requirements 3–5) — target ≤ 15 minutes

Record in this order, editing out waiting time:

1. **(0:00) Intro + CI/CD concept** — use section 1.
2. **(1:30) DigitalOcean Droplet** — show it created (section 2).
3. **(2:30) SSH access** — connect to the Droplet (section 3).
4. **(3:30) GitLab self-hosting** — show GitLab running on the Droplet (section 4).
5. **(5:00) Repository + push** — push your code, pipeline auto-starts (section 5).
6. **(7:00) SonarQube** — show the setup and the analysis results: bugs, vulnerabilities, code smells (section 6).
7. **(9:30) CI/CD pipeline** — show all four stages green and a job log (section 7).
8. **(11:30) Jira + GitLab integration** — show them connected (section 8).
9. **(13:00) Issue automation** — a commit updating a Jira issue live (section 9).
10. **(14:00) Summary** — recap the full workflow.

Submit only the **YouTube link** (Public or Unlisted).

---

## 11. Security — hide these before recording (Requirement 7)

Blur, hide, or regenerate anything sensitive that appears on screen:

- **Your Gmail SMTP password** in `api/.env` — this is currently a real app password (`SMTP_PASS`). Never show the `.env` contents on camera, and consider regenerating it after the project.
- Private SSH keys, GitLab root password, SonarQube token, Jira API token.
- DigitalOcean billing details.

All real secrets belong in **GitLab CI/CD Variables (masked)** or the server's `.env` — never in the repository or on screen.

---

## 12. Assessment criteria checklist (Section 8)

| # | Criterion | Where it's covered |
|---|-----------|--------------------|
| 1 | Understanding of CI/CD | Section 1 (video intro) |
| 2 | DigitalOcean Droplet setup | Section 2 |
| 3 | SSH access | Section 3 |
| 4 | GitLab self-hosting | Section 4 |
| 5 | Repo + source management | Section 5 |
| 6 | SonarQube setup + analysis | Section 6 |
| 7 | Working CI/CD pipeline | Section 7 + `.gitlab-ci.yml` |
| 8 | Jira ↔ GitLab integration | Section 8 |
| 9 | Issue-tracking automation | Section 9 |
| 10 | Video clarity + professionalism | Section 10 |

---

## Quick reference — the files in your repo

- `.gitlab-ci.yml` — the pipeline. Edit stages/jobs here.
- `sonar-project.properties` — what SonarQube scans (key must match the SonarQube project).
- `student-task-tracker/api/.env` — secrets; git-ignored; never commit or show.

> Note: exact button labels and menu paths in DigitalOcean, GitLab, SonarQube, and Jira change over time. If a screen looks different from this guide, check the official docs for that tool — the concepts and commands remain the same.
