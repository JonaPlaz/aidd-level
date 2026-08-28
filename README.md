# aidd-level

Évalue le niveau **AI-Driven Development** d'un profil de développeur sur la grille AIDD
(❖ White → 🥇 Gold) et dit ce qui a mené là et comment monter d'un cran.

## Lancer

```
docker build -t aidd-level .
docker run --rm aidd-level evaluate profiles/arthur
```

Sortie : le niveau, l'axe qui plafonne avec chaque fait et l'endroit où il se constate, puis le
geste vers le niveau suivant. Aucune clé d'API, aucun réseau.

## En construction

Projet du hackathon [LAIVEL UP](https://github.com/ai-driven-dev/laivel-up) (28–31 août 2026).
Les spécifications sont dans `docs/specs/`, la preuve de calibration dans
`docs/calibration.md`, le plan dans `ROADMAP.md`. Le détail de ce README arrive avec le code.
