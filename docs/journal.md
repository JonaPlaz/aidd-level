# Journal d'exécution

Ce qui n'a **pas** produit de commit : tentative abandonnée, liaison cassée, repli, borne
atteinte, choix d'allocation revu. Git, les PR, les commentaires de review et la CI
journalisent le reste. Fichier **append-only**, une ligne par événement, chaque ligne porte un
pointeur vérifiable — sans pointeur elle ne vaut rien.

| horodatage | étape / chantier | acteur | événement | pointeur | suite |
|---|---|---|---|---|---|
