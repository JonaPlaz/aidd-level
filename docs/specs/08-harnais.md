# 08 — Harnais de développement

Objectif (Jonathan, 2026-08-26) : **le dépôt rendu atteint lui-même le niveau Gold de la
grille qu'il applique** — context engineering, behavior, boucles ; intervention « jamais, une
fois la tâche cadrée » ; trois chantiers de front. Et le critère « comment tu l'as
construit ? » note ce harnais **s'il est montré** : versionné, décrit dans `docs/methode.md`,
constaté dans `docs/harness.md`, visible dans la vidéo.

Décision de fond : **harnais maison, framework AIDD écarté** (examiné en v5.9.0 ; motif : le
critère est écrit à la deuxième personne, et 47 skills à apprendre sur la fenêtre est le seul
pari capable de coûter le rendu). Deux emprunts de forme, cités comme art antérieur :
déclenchement par label, et « iron rule » anti-retour.

## 1. La boucle

```
issue `to-implement`
  └─► skill /feature <n°>
        ├─► agent spec : la spec existe-t-elle dans docs/specs/ ? sinon l'écrit → ARRÊT humain
        ├─► agent dev (worktree) : branche, code, tests, `make test lint dup`, push, PR, label `to-review`
        ├─► CI : tests + analyse statique + duplication
        ├─► Codex : revue automatique à l'ouverture (réglage cloud, seul geste manuel)
        ├─► skill : rebase, revue Codex, passes de correction jusqu'à validation
        └─► `gh pr merge --auto --squash --delete-branch` — merge quand les verrous sont verts
```

Deux harnais, jamais en contact direct : **Claude Code implémente, Codex review**, la PR est
le seul rendez-vous. Pas d'agent reviewer maison (doublon de Codex).

**Arbitrage proposé — la correction tourne en local, pas en CI.** Le brief le laisse « à
trancher au montage ». Motifs : aucun secret d'API dans le dépôt (case obligatoire du rendu),
coût dans l'abonnement, le piège `allowed_bots` (issue #1299) disparaît avec le bot, et le flux
est identique vu du dépôt. Conséquence : `claude-code-action` n'est pas installée.
**À valider par Jonathan.**

## 2. Agents — deux, chacun avec son allocation justifiée

Champs vérifiés le 2026-08-28 (doc *sub-agents*) : `model`, `effort`, `maxTurns`,
`permissionMode`, `isolation`, `tools`, `skills`, `hooks`. Principe : **l'effort va où se
prennent les décisions, pas où se produit le volume.**

| | `spec` | `dev` |
|---|---|---|
| Fichier | `.claude/agents/spec.md` | `.claude/agents/dev.md` |
| `model` | `opus` | `sonnet` |
| Pourquoi | une spec mal raisonnée se paie sur toute la chaîne | travail cadré par une spec validée, exécutable par un modèle plus petit |
| `effort` | `high` | `medium` |
| Pourquoi | raisonnement contrastif, seuils, cas dégradés | implémentation guidée ; `high` si un test de calibration casse (relance manuelle) |
| `maxTurns` | 40 | 80 |
| Pourquoi | borne 1 ; une spec tient en une session courte | borne 1 ; code + tests + PR + une passe de correction |
| `permissionMode` | `acceptEdits` | `acceptEdits` |
| Pourquoi | écrit dans `docs/specs/` sans demander ; ne lance rien d'externe | édite et lance `make`, `git`, `gh` ; les hooks gardent les couches |
| `isolation` | — | `worktree` |
| `tools` | Read, Grep, Glob, Write, Edit, WebFetch, WebSearch | Read, Grep, Glob, Write, Edit, Bash |
| `skills` | — | `feature` |

⚠️ **Non vérifié** : le comportement quand `effort` n'existe pas pour le modèle demandé. À
constater sur la PR jetable. Les valeurs de `maxTurns` ne sont sourcées nulle part :
adaptation assumée, revue au journal si une borne est atteinte.

## 3. Skills — deux

| Skill | Frontmatter | Rôle |
|---|---|---|
| `bootstrap` | `disable-model-invocation: true`, `allowed-tools: Bash Write Read` | § 07, une fois |
| `feature` | `argument-hint: [issue-number]` (invocable par le modèle depuis le 2026-08-30, chantier 16) | enchaîne spec → validation → dev → PR → attente de review → correction → merge auto, sans intervention après validation. Mode `--trivial` pour la PR jetable |

**Attente de review (local)** : l'agent `dev` rend la main dès la PR ouverte ; **le skill
possède la boucle** (une seule responsabilité, remarque Codex sur la PR #13). Les opérations
git sur la branche sont exécutées par **le propriétaire du checkout** (agent `dev` relancé,
ou skill pour une PR née du checkout courant) ; jamais de `git -C`.

**Une revue Codex par PR, à l'ouverture** — arbitré par Jonathan le 2026-08-29, après
épuisement du quota de revues en une soirée (3 à 5 re-revues par PR). Le skill attend le
verdict d'ouverture (revue ou réaction 👍 ; `@codex review` ne sert qu'aux re-revues
exceptionnelles) avec `gh api --paginate` sur `…/reviews`, `…/comments` et
`…/issues/{n}/reactions`, plafond `REVIEW_WAIT_MAX = 20 min` (valeur initiale d'après la PR
#13 : revue reçue ≈ 12 min après le dernier push ; délais suivants consignés dans
`docs/harness.md`). Puis **une passe de correction** qui traite toutes les remarques, **rebase et push
d'abord** (amendé le 2026-08-30, remarque Codex sur la PR #38 : un rebase après coup réécrit
le SHA cité), puis **une réponse tracée par remarque** dans le fil de la PR (appliqué en
`<sha>` présent sur la branche, ou non appliqué et motif), et seulement alors
`gh pr merge --auto`. **Rien ne s'arme avant le verdict
d'ouverture** — c'est le point que Jonathan a fixé : GitHub ne merge jamais avant que Codex
ait eu le temps de revoir. Re-revue jamais automatique : seulement si la correction change
une décision de scoring, ou sur demande. Délai dépassé ou quota épuisé → journal, label
`blocked`, arrêt.

**Armement par la plateforme, pas par l'agent** (amendé le 2026-08-30, chantier 15). Constat
du jour : deux PR ouvertes à la main, hors skill, n'ont armé personne — le 👍 de Codex est
resté sans suite jusqu'à ce qu'un humain s'en aperçoive. Le verdict Codex est un événement
GitHub, distant, invisible d'un hook Claude Code ; et une réaction ne déclenche **aucun**
événement Actions (seuls revues et commentaires en déclenchent). Donc un workflow
`.github/workflows/auto-merge-after-codex.yml`, en **cron** (toutes les 10 min) et à la
demande, qui, pour chaque PR ouverte, non brouillon, sans label `blocked`, dont l'auteur de la
réaction `+1` est `chatgpt-codex-connector[bot]` **et** dont cette réaction est postérieure au
dernier commit de la branche, arme `gh pr merge --auto --squash --delete-branch`. GitHub merge
ensuite seul quand `ci` est vert et la branche à jour (`strict`). Le chemin « avec remarques »
reste au skill : Actions ne sait pas juger qu'une remarque est traitée ; le skill corrige,
répond, puis arme comme avant. « Rien ne s'arme avant le verdict d'ouverture » tient : le
cron ne lit que le 👍.

Historique : « une passe » (26 août) → « autant de passes que Codex en demande » (29 août,
PR #15) → « une revue, une passe, réponses tracées » (29 août, quota). Chaque étape est au
journal.

**Le cycle est imposé, pas rappelé** (amendé le 2026-08-30, chantier 16). Constat : quatre PR
ouvertes à la main en une matinée, hors skill, avec à chaque fois un pas du cycle oublié
(label, armement, merge synchrone tenté). `disable-model-invocation: true` sur `feature`
(posé au chantier 0 sans motif écrit) obligeait un humain à taper `/feature` — l'inverse du
but. Levé : la session lance le skill elle-même. Et le hook `guard-git` refuse `gh pr create`
hors d'un run du skill et tout `gh pr merge` sans `--auto` ni `--disable-auto`. Le run se
prouve par un **verrou qui lui appartient** (remarque Codex sur la PR #41 : un verrou unique
partagé cassait les runs concurrents en worktrees) : `<git common dir>/feature-locks/<n°>`,
posé par `node .claude/hooks/feature-lock.js lock <n°>` au premier geste du skill, retiré
par `unlock <n°>` au dernier, visible depuis tous les worktrees, jamais committé ; il
n'autorise que les branches portant ce numéro (`feat/<n°>-…`, `docs/spec-<n°>` ;
`trivial-<horodatage>` ↔ `trivial/…`). Les deux commandes sont dans la liste `allow` de
`.claude/settings.json` : le run ne s'arrête pas pour une permission. Les flags globaux de
`gh pr` (`-R/--repo`) sont parsés avant le sous-commande, jamais supposés absents. Une PR de
docs passe aussi par une issue et `/feature`.

**Iron rule** : une fois l'agent dev engagé sur une issue, le skill ne revient pas au routage ;
il termine, ou s'arrête sur une borne.

## 4. Hooks — quatre, tous testés par violation

Versionnés dans `.claude/settings.json` (sinon ils ne comptent pas), scripts dans
`.claude/hooks/*.js` (Node, présent sur la machine — **version non vérifiée**). Format vérifié :
`PreToolUse` bloque par exit 2 ; entrée JSON sur stdin (`tool_name`, `tool_input`, `cwd`).

| Hook | Événement · matcher | Règle | Test de déclenchement (doute 7) |
|---|---|---|---|
| `guard-layers.js` | `PreToolUse` · `Edit\|Write` | fichier sous `src/Domain/` dont le contenu contient `use AiddLevel\Application` ou `use AiddLevel\Infrastructure` → exit 2 | écrire volontairement un `use` interdit, constater le refus |
| `guard-git.js` | `PreToolUse` · `Bash` (`git commit`, `git push`, `gh pr create`, `gh pr merge`) | commit : fichiers (index, ou `-a`) touchant `src/Domain/` **et** `src/Infrastructure/` → exit 2 ; chemin déclaré dans `.worktreeinclude` → exit 2. Push : `--force` ou `-f` nu → exit 2 (`--force-with-lease` seul admis, pour le rebase). `gh pr create` : branche courante sans verrou `feature-locks/<n°>` correspondant → exit 2. `gh pr merge` sans `--auto` ni `--disable-auto` → exit 2 (chantier 16) | `.claude/hooks/tests/guard-git.test.js`, exécuté par la CI : deux couches stagées ; `git push --force` ; `gh pr create` sans verrou, avec le verrou d'un autre run, avec `-R` avant le sous-commande ; `gh pr merge --squash` ; et les voisins admis |
| `format.js` | `PostToolUse` · `Edit\|Write` | fichier `*.php` → `make fmt FILE=…` (php-cs-fixer dans Docker, **version non vérifiée** pour PHP 8.5) | éditer un fichier mal indenté, constater la réécriture |
| `journal.js` | `PostToolUseFailure` · `Bash` ; `SubagentStop` ; `Stop` (seulement hors `main` avec travail non committé) | ajoute une ligne à `docs/journal.md` (format § 6) avec `git rev-parse HEAD`, branche, et pour `PostToolUseFailure` la commande échouée — **le journal est alimenté par hook, pas par la bonne volonté du modèle** | faire échouer `make test`, constater la ligne |

Un hook qui ne se déclenche pas au test est **retiré**, pas laissé mort.

`permissions` dans le même `settings.json` : `allow` sur `make`, `git` (status, diff, log,
add, commit, push, fetch, rebase, checkout, switch), `gh` (pr, issue, api, run) et `sleep`
(boucle d'attente) ; `deny` sur `rm -rf`, `Edit`/`Write` de `.brief/`. `Read(./.brief/**)`
est **exclu** du deny (le brief doit rester lisible). Le `deny` `git push --force:*` posé au
premier montage est **retiré** : il bloquait aussi `--force-with-lease`, requis par le rebase ;
c'est le hook `guard-git` qui refuse `--force`, `-f` et les refspecs `+…`.
`worktree.baseRef` reste `fresh` (branche depuis `origin/main`).

## 5. CI — `.github/workflows/ci.yml`

Déclenchée sur `pull_request` et `push` sur `main`. Un job `ci` :
`shivammathur/setup-php@v2` (PHP 8.5 — **disponibilité du tag non vérifiée**, repli : job
dans le conteneur `php:8.5-cli-alpine`), `composer install --no-progress`, `make test`,
`make lint` (PHPStan, niveau max), `make dup` (duplication, seuil en constante
`DUPLICATION_MAX_PCT` dans le Makefile, **valeur non sourcée** — proposition 3 %, à égalité
avec le meilleur profil fourni ; à valider). Résultat visible publiquement. Après le premier
run vert, second `PUT` de protection avec `required_status_checks.contexts = ["ci"]` et
`strict: true`.

## 6. Journal — `docs/journal.md`

Auditabilité, pas observabilité. Git, PR, commentaires Codex, CI journalisent déjà ; le
fichier tient **ce qui n'a pas produit de commit**. Append-only, une ligne par événement :

```
| horodatage ISO | étape ou chantier | acteur (agent · modèle · effort) | événement | pointeur (SHA, PR, run, chemin) | suite |
```

Une ligne sans pointeur ne vaut rien (même réserve que le projet applique au déclaratif).
Conventions GenAI OpenTelemetry : connues, écartées (disproportionnées), citées dans la méthode.

## 7. Bornes — où la boucle s'arrête

1. `maxTurns` par agent (§ 2) ;
2. **une** revue Codex par PR et **une** passe de correction, réponses tracées (2026-08-29,
   après un passage par « autant de passes qu'il faut » qui a épuisé le quota) ;
3. arrêt net : tuer les sessions, les worktrees gardent leur travail ; `enforce_admins: false`
   laisse Jonathan merger à la main.

**Un seul point d'arrêt humain : la validation d'une spec.** Point de non-retour : une heure
d'écart entre montage prévu et réel → boucle en manuel (Claude implémente, Jonathan lance la
review Codex, Claude corrige) ; `docs/harness.md` le dit.

## 8. Parallélisme et intégration

Le degré se **calcule** depuis le graphe de dépendances de `ROADMAP.md` : deux chantiers sont
simultanés si leurs specs ne partagent aucun fichier de sortie et qu'aucun ne dépend de
l'autre. Attendu : noyau de domaine seul, puis quatre axes de front, puis sortie/robustesse.

- Rebase sur `main`, jamais de merge de `main` dans la branche ; `required_linear_history` ;
  push par `--force-with-lease` uniquement (le check requis est `strict`, une branche en
  retard ne merge pas — message GitHub constaté sur #15).
- Branches courtes : une issue, une PR, squash au merge.
- `ROADMAP.md` et `docs/journal.md` append-only ; `composer.json` par un seul chantier à la
  fois (dépendance déclarée dans la roadmap).
- Une tentative de rebase automatique par l'agent qui possède la branche, puis `blocked`.
- ⚠️ **Non éprouvé** : trois worktrees simultanés et le rebase concurrent (doute 6). Trois
  chantiers triviaux avant les vrais.

## 9. PR jetable — lever les doutes 1 à 4 avant le premier vrai chantier

`/feature --trivial` : une ligne ajoutée au README, branche, PR, label. Observer :
(1) Codex publie-t-il ? (2) le skill voit-il la revue et corrige-t-il ? (3) forme de la sortie
Codex — check ou commentaires ; (4) sans objet en local (pas de bot). Chaque constat va au
journal avec l'URL de la PR ; `REVIEW_WAIT_MAX` est fixé d'après le délai mesuré.

## 10. Ce que la vidéo montre

Une issue étiquetée, le skill lancé, la PR ouverte avec la revue Codex, le merge automatique,
puis `docker run … evaluate profiles/self` sur le dépôt lui-même. Deux minutes, muette,
lisible sans le son (texte à l'écran, pas de voix).
