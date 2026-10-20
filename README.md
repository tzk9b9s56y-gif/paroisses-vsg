# Thème « Ensemble paroissial Notre-Dame de Lourdes – Sainte-Geneviève »

Thème WordPress classique pour le site commun des paroisses Notre-Dame de Lourdes et Sainte-Geneviève (chapelles Saint-Louis et Saint-Joseph). Aucune extension n'est nécessaire.

## Ce que fait le thème

- **Accueil** (`front-page.php`) : portail des paroisses, prochains rendez-vous, actualités, feuilles de la semaine.
- **Paroisses** : une page avec le modèle « Paroisse – présentation » ; ses pages enfants deviennent les onglets. Modèles disponibles dans `templates/` : actualités et agenda, feuille paroissiale, informations pratiques, sacrements, galerie. Les autres pages enfants (catéchèse, chaque sacrement) utilisent `page.php`.
- **Chapelles** : pages enfants d'une paroisse avec le modèle « Chapelle ».
- **Groupes et communautés** : dans chaque paroisse, une page « Communautés » (modèle « Communautés ») ; chaque communauté est une page enfant avec le modèle « Communauté ». Champs : rendez-vous réguliers, lieu, contact, site et réseaux, catégorie liée (sous-catégorie de la paroisse) pour afficher ses rendez-vous et actualités. Une page commune avec le même modèle « Communautés », placée à la racine, les regroupe toutes. Astuce : choisir le modèle puis enregistrer la page pour faire apparaître les champs.
- **Horaires des messes** : saisis dans la fiche de chaque lieu (église ou chapelle), champs « En semaine », « Samedi », « Dimanche », « Confessions ». La page commune avec le modèle « Horaires des messes » les regroupe ; le bouton « Horaires des messes » y mène. MessesInfo reste en lien secondaire, à tenir à jour en parallèle.
- **Couleur par paroisse** : réglée dans la fiche de la paroisse ; sert au bandeau, à l'onglet actif et aux pastilles.
- **Menus dépliables** : sur tous les écrans, les rubriques de paroisse et les filtres par lieu sont des menus déroulants (balise `details`, sans JavaScript) ; le menu principal est un menu « burger ».
- **Mot du curé et histoire** : le mot du curé (champs nom, texte, photo, dans la page d'accueil du site) s'affiche une seule fois, sur l'accueil ; la page enfant « Histoire de la paroisse » (modèle « Paroisse – histoire ») contient l'histoire puis les lieux de culte. Le résumé de la page paroisse (panneau « Extrait ») sert d'introduction.
- **Rencontrer un prêtre** : bloc sur la page Horaires et dans Informations pratiques ; le bouton ouvre le formulaire de contact avec le motif déjà choisi (`?objet=pretre`). Le formulaire a un champ « Votre demande » (prêtre, sacrement, intention de messe, autre), repris dans l'objet du mail.
- **Prier** : page-menu (modèle « Prier – vie de saints ») qui liste ses pages enfants, une par saint (texte, vidéo YouTube en bloc HTML avec lien, prière). Les prières aux saints de la démo sont des textes proposés, à valider par le curé.
- **Navigation en menus** : l'accueil et la présentation de chaque paroisse affichent d'abord des tuiles « Que cherchez-vous ? ». La page commune « Baptême, mariage, funérailles » (modèle « Choix de la paroisse ») fait choisir la paroisse avant le sacrement. La page Horaires montre les quatre lieux ; le menu « Lieu » n'en affiche qu'un (`?lieu=`).
- **Agenda** : type de contenu `evenement` (date, heure, lieu précis dans la boîte « Date et lieu »).
- **Feuilles paroissiales** : type `feuille` ; le PDF joint au contenu est détecté automatiquement.
- **Rattachement** : chaque contenu est classé dans une catégorie (« Paroisses et lieux »). Une catégorie par paroisse, les chapelles en sous-catégories, plus « Les deux paroisses » qui s'affiche partout. Chaque page paroisse ou chapelle indique sa catégorie dans « Coordonnées affichées sur le site ».
- **Contact** : formulaire intégré (`inc/contact.php`), envoi par `wp_mail` à l'adresse de la paroisse, rien n'est stocké.

## Organisation des fichiers

```
functions.php          chargement et réglages
inc/helpers.php        constantes, puis chargement de inc/helpers/ :
  helpers/dates.php      dates en français, lecture des champs
  helpers/lieux.php      paroisses, chapelles, catégories, couleurs, liens
  helpers/horaires.php   horaires des messes, contacts des prêtres
  helpers/agenda.php     rendez-vous, actualités, feuilles
  helpers/groupes.php    communautés et groupes de prière
  helpers/rendu.php      cartes, tuiles, filtres, images
  helpers/fil-ariane.php fil d'Ariane
inc/content-types.php  agenda et feuilles
inc/meta-boxes.php     champs de saisie
inc/contact.php        formulaire de contact (intentions.php, messes.php : les deux autres)
inc/dons.php           dons en ligne (SumUp)
inc/import.php         import et export de l'agenda (Excel)
inc/securite.php       limites des formulaires et des connexions
inc/seo.php            titres de page et description
inc/htaccess.php       règles de protection du serveur
inc/mail.php           envoi des mails (SMTP)
inc/maintenance.php    mode maintenance
inc/admin.php          tableau de bord simplifié
inc/updater.php        version publiée sur GitHub
inc/auto-update.php    installation automatique, sauvegarde et retour arrière
inc/upgrade.php        mises à jour du contenu entre deux versions
templates/             modèles de page
template-parts/        bandeau de paroisse
assets/admin.css       styles des pages d'administration
assets/js/             site.js (touche Échap, focus des erreurs), admin.js
assets/fonts/          polices hébergées localement (licence OFL)
```

## Maintenance

- Mettre à jour WordPress régulièrement (mises à jour automatiques conseillées).
- Le thème n'utilise pas d'extension : peu de risques de casse lors des mises à jour.
- Envoi des mails : Réglages › Général (adresse d'expédition et serveur SMTP de la boîte).

## Mises à jour

Pour publier une version : modifier le code, augmenter `Version:` dans `style.css`, pousser sur `main`.

- GitHub prévient le site (webhook, Apparence › Mises à jour du thème) : la version est installée dans la minute.
- Sinon, le site vérifie toutes les heures.
- Seule l'archive du commit dont la version a été lue est installée. Le thème en place est copié avant ; si l'accueil ne répond plus après l'installation, la copie est remise automatiquement. Tout est noté dans le journal de la page.

## Vérifications automatiques

À chaque envoi sur GitHub (`.github/workflows/verifications.yml`) : syntaxe de tous les fichiers PHP et feuille de style complète (bloquants), règles de sécurité WordPress avec PHPCS (informatif).
