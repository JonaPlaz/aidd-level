# Méthode — ce qu'on mesure, pourquoi, et comment ça a été construit

## Ce qu'on mesure

Un profil est un dossier de mesures ; la pièce qui décide est `git-activity.json`. Chacun des
quatre axes de la grille AIDD se lit dans un champ, et un seul :

| Axe | Signal | Règle |
|---|---|---|
| Taille | `pull_requests.median_files_changed` (repli : `median_lines_changed`) | S 1–2 · M 3–8 · L 9–20 · XL > 20 fichiers. Les fichiers avant les lignes : `bohort` fait 251 lignes (L) pour 7 fichiers (M) et elle est Blue = M |
| Harness | `context_files.*` + recherche d'une relance bornée dans `repo-context/` | mémoire → context engineering ; règles/skills/hooks/agents → behavior ; relance **et** borne dans un script → boucles. Jamais la marque : les compteurs sont indépendants de l'outil |
| Intervention | `pull_requests.median_correction_commits_after_open` | ≥ 3 majorité · 2 partie · 1 étapes clés · 0 jamais (sur ≥ 12 PR) |
| En parallèle | `parallelism.median_concurrent_branches` | la médiane, jamais le maximum : `bohort` a un pic à 3 pour une médiane de 1 |

**Le niveau est le minimum des quatre axes** (« un niveau n'est atteint que si tous ses axes le
sont »). L'axe qui plafonne est du même coup l'explication et le chemin : le même calcul
produit la note et son pourquoi, rien n'est reconstruit après coup.

**Pourquoi ces seuils.** Aucun n'est dans la grille. Les bandes de taille viennent des mesures
publiées sur les PR après adoption de l'IA (médiane 66 → 210 lignes ; 20 fichiers / 1 000
lignes, le point où la revue casse). Les seuils d'intervention traduisent mot pour mot la
colonne « ce qu'on observe ». Tous vivent dans des constantes nommées de `src/Domain/Threshold/`,
avec leur origine en commentaire — ce qui est une adaptation le dit. Éprouvés sur les quatre
profils fournis **sans ajustement** : 4/4 (`docs/calibration.md`), et sur dix profils fictifs
couvrant ce que le jeu fourni ne prouve pas (Silver, White, échantillons courts, champs absents).

**Ce qu'on ne mesure pas.** Le déclaratif n'entre dans aucun calcul (`perceval` se dit avancé
pour 4 % de commits IA). Sonar est cité, jamais jugé : la qualité est le prérequis, pas un axe.
`pull-requests.json` est inventorié, pas comparé : ses commits par PR ne sont pas des commits
correctifs.

## Comment on explique

Cinq règles, tirées de la littérature sur l'explication (Miller ; Karimi et al.) : contrastive
(« pourquoi Red plutôt que Blue »), sélective (seul l'axe qui bloque explique ; un ex æquo se dit
comme tel), **sourcée** (chaque ligne porte `fichier › champ = valeur` — une affirmation sans
pointeur ne se construit pas dans le code), actionnable (un geste, pas une case manquante), et
ordonnée par ce qui se change vraiment : Harness d'abord, c'est un artefact qu'on écrit
aujourd'hui ; la taille et la reprise sont des conséquences, on ne les décrète pas.

## Quand on ne sait pas

Trois statuts, jamais une exception : **non évaluable** (gate : dossier, `profile.json`,
`git-activity.json`, au moins une PR — le prérequis manquant est nommé) ; **évalué, confiance
basse** (échantillon sous le plancher, ou champ décideur absent : fourchette et manque
chiffré, « fournir le champ » avant tout geste) ; **évalué**.

## Ce que l'outil dit de son propre dépôt

`profiles/self/` est fabriqué depuis l'API GitHub par `scripts/self-profile.py`, régénéré en
fin de chantier (17 PR). Verdict : **Blue, par Taille** — 7 fichiers par PR en médiane, la
bande M ; Intervention est à 1 commit correctif après ouverture (« aux étapes clés »),
Harness à behavior sans boucle, En parallèle à 1,5. Le chemin vers Green est donc la taille
habituelle des chantiers — et la table des gestes le renvoie, comme prévu, au dispositif :
la taille suit le harnais, elle ne se décrète pas.

Deux leçons mesurées sur ce profil. Les commits correctifs sont ceux de la boucle de revue
(Codex commente, Claude Code corrige, aucune main humaine) : le proxy agrégé les lit comme
des reprises — limite assumée de l'axe (spec 03), constatée en vrai. Et la première version
du script lisait Red : elle datait les commits par `committer.date`, que chaque rebase
réécrit ; Codex l'a vu, `author.date` a corrigé. Le dépôt porte désormais le trailer sur
chaque commit d'agent (ratio 0,51 mesuré avant la règle).

## Comment ça a été construit

Un seul intrant : un brief. Tout le reste a été fabriqué par la session — specs d'abord
(`docs/specs/`, premier commit), validation humaine (le seul arrêt), puis amorçage, harnais,
plan, issues, code. Deux harnais qui ne se parlent que par la PR : **Claude Code implémente**
(deux agents, `spec` et `dev`, avec modèle, effort et bornes déclarés ; deux skills ; quatre
hooks éprouvés par violation ; CI), **Codex revoit**. Six chantiers de front après le noyau de
domaine, en worktrees, intégrés en cascade. La règle de boucle a changé trois fois en une nuit
— une passe, puis illimitée, puis une revue par PR avec réponses tracées — chaque revirement
daté et motivé dans `docs/journal.md` ; le flow tel qu'il a **réellement** tourné est dans
`docs/harness.md`.

**Examiné et écarté** : le framework AIDD (v5.9.0 — le critère demande *ton* harnais, et 47
skills à apprendre sur 72 h était le seul pari capable de coûter le rendu ; deux idées reprises,
le déclenchement par label et l'« iron rule ») ; les conventions GenAI d'OpenTelemetry (le jury
juge l'auditabilité, pas la latence : un journal à pointeurs suffit). **Un seul geste manuel** :
l'activation de la revue Codex, réglage web sans commande.
