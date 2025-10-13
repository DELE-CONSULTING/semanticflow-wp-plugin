# SemanticFlow Wordpress Plugin

# Installation

### Via le tableau de bord WordPress

1. Téléchargez le fichier : [https://github.com/DELE-CONSULTING/semanticflow-wp-plugin](https://github.com/DELE-CONSULTING/semanticflow-wp-plugin)

2. Dans WordPress, allez dans **Extensions** → **Ajouter**
3. Cliquez sur **Téléverser une extension**
4. Sélectionnez votre fichier ZIP et cliquez sur **Installer maintenant**
5. Cliquez sur **Activer l'extension**


## Configuration de l'ID du projet

1. Dans votre tableau de bord WordPress, allez dans **Réglages** → **SemanticFlow Tracker**
2. Entrez votre **ID de projet**  dans le champ prévu.
3. Cliquez sur **Enregistrer les modifications**

### Simuler une visite de bot LLM (pour les tests)

Vous pouvez utiliser curl pour simuler une visite de bot sur votre site :

```bash
curl -A "Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.0" \
  https://votre-site.com/votre-article/
```

### Sécurité et confidentialité

- Le plugin respecte les adresses IP derrière des proxies et CDN
- Les données sont envoyées via HTTPS
- Aucune donnée personnelle sensible n'est collectée
- Compatible avec le RGPD (collecte de données analytiques uniquement)