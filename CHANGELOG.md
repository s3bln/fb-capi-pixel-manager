# Changelog

All notable changes to **FB CAPI & Pixel Manager** are documented here.

---

## [2.4.3] — 2026-03

### Corrections
- Admin : les champs du formulaire affichaient l'ancienne valeur après sauvegarde (cache statique de `FB_Capi_Options::get()` rempli avant le save). Cache invalidé via `invalidate_cache()` après chaque `update_option()`.

---

## [2.4.2] — 2026-03

### Corrections
- PageView serveur en double sur la page panier WooCommerce : ignoré pour les requêtes XHR/fetch (en-tête `X-Requested-With: XMLHttpRequest`).

---

## [2.4.1] — 2026-03

### Corrections
- PageView serveur en double sur WooCommerce : ignoré pour les requêtes de préchargement navigateur (`Sec-Purpose: prefetch` / `Purpose: prefetch`) et les requêtes WooCommerce frontend AJAX (`?wc-ajax=`).

---

## [2.4.0] — 2026-03

### Ajouts
- **Advanced Matching SureCart** : email, prénom, nom (et adresse si renseignée) maintenant envoyés à Meta via expansion API en deux étapes — `Purchase::with(['initial_order'])` puis `Checkout::with(['billing_address'])`.
- Webhook SureCart : quand platform=surecart, retourne 200 sans envoyer à Meta — le hook PHP est l'unique canal Purchase, éliminant le double comptage.
- `event_source_url` corrigé : utilise `HTTP_REFERER` (URL checkout réelle) au lieu de `REQUEST_URI` (URL API SureCart).
- Filtre `is_object($webhook_data)` : ignore la requête serveur SureCart (webhook interne), traite uniquement la requête navigateur qui dispose des cookies fbp/fbc.

### Corrections
- Double envoi à Meta éliminé : SureCart déclenche `surecart/purchase_created` deux fois (requête navigateur + webhook serveur) — le filtre `is_object` résout le problème proprement sans verrou MySQL.
- Expansion API en deux appels séquentiels (contourne la limite de 1 niveau effectif de SureCart).

---

## [2.3.0] — 2026-03

### Ajouts
- **SureCart Purchase via hook PHP** (`surecart/purchase_created`) : remplace la dépendance exclusive au webhook qui ne fournissait qu'un payload minimal (montant toujours à 0).
- `event_source_url` corrigé pour SureCart : `HTTP_REFERER` au lieu de `REQUEST_URI`.

---

## [2.2.6] — 2026-03

### Corrections
- Webhook SureCart : signature corrigée — SureCart signe `"{timestamp}.{body}"` (format Stripe-like), pas le body seul. Le header `x-webhook-timestamp` est maintenant inclus dans le payload signé.

---

## [2.2.5] — 2026-03

### Corrections
- Webhook SureCart : extraction des données corrigée (`data.object` au lieu de `data`) pour correspondre à la structure réelle du payload SureCart
- Webhook SureCart : `trim()` sur le secret et `strtolower()` sur le hash reçu pour absorber les variantes de format ; logs détaillés (hashes complets + longueur du body) pour diagnostiquer les éventuels échecs de signature

---

## [2.2.4] — 2026-03

### Corrections
- Filtre anti-doublon JS restauré et corrigé : utilise `window.fbq.callMethod` (posé par le SDK après chargement) au lieu du stub d'origine, ce qui permettait aux événements SureCart sans `eventID` de passer
- Webhook SureCart : vérification élargie à tous les noms d'en-tête possibles (`X-SC-Signature`, `X-Surecart-Signature`, etc.) et gestion du préfixe `sha256=` selon les versions ; logs d'erreur ajoutés pour diagnostiquer les échecs de signature

---

## [2.2.3] — 2026-03

### Corrections
- Suppression du filtre anti-doublon JS : il interceptait `window.fbq` avant que le SDK Facebook charge, empêchant les événements navigateur (AddToCart, InitiateCheckout…) de s'envoyer après le chargement du SDK. La déduplication est assurée côté Meta via le `event_id` partagé entre pixel et CAPI.

---

## [2.2.2] — 2026-03

### Corrections
- PageView serveur envoyé en double sur les sites dont le thème ou un plugin déclenche `wp_footer` plusieurs fois : guard statique ajouté dans `send_server_pageview()`

---

## [2.2.1] — 2026-03

### Améliorations
- PageView serveur : utilisation de `fastcgi_finish_request()` sur les hébergements PHP-FPM — la réponse est envoyée au navigateur avant l'appel CAPI, éliminant toute latence côté visiteur tout en conservant la vraie réponse Meta dans les logs

---

## [2.2.0] — 2026-03

### Corrections
- Correctif CSS admin : bump de version pour invalider le cache navigateur sur les styles des boutons radio de sélection de plateforme

---

## [2.1.0] — 2026-03

### Améliorations
- Optimisation des performances : cache statique des options, PageView serveur en mode non-bloquant, sortie anticipée dans les événements JS
- Table des logs : la colonne Réponse n'affiche plus le JSON brut pour les succès — uniquement le message d'erreur pour les entrées en échec
- Sélecteur de plateforme : 3 boutons radio avec détection automatique des plugins installés ; les options non disponibles sont grisées et non sélectionnables

### Corrections
- Token CAPI rejeté silencieusement si il contenait des caractères base64 (`.`, `/`, `+`, `=`)
- Événements PageView absents des logs (statut `SENT` non reconnu par le parser)
- `logs_events` remis à zéro à chaque sauvegarde des réglages
- `$platform` utilisé avant d'être défini dans l'injection JS (notice PHP)
- Détection d'erreur locale possible même en mode async (`WP_Error`)

---

## [2.0.0] — 2026-03

### Ajouts
- **Intégration WooCommerce** : événement Purchase via hook `woocommerce_thankyou`, sans webhook à configurer. Pixel navigateur injecté sur la page de confirmation pour la déduplication.
- **Advanced Matching** : téléphone, prénom, nom, ville, région, code postal, pays — normalisés et hashés SHA-256 avant envoi à Meta. Disponible pour SureCart (webhook) et WooCommerce.
- **Paramètres e-commerce enrichis** : `content_ids`, `content_type` et `num_items` extraits des lignes de commande pour Purchase ; `content_ids` extrait du DOM pour ViewContent et AddToCart.

---

## [1.0.0] — 2026-02

### Ajouts
- Première version du plugin (conversion depuis un snippet mono-fichier)
- Intégration SureCart : Pixel JS navigateur + Conversions API serveur pour les événements PageView, ViewContent, AddToCart, InitiateCheckout, Purchase (webhook HMAC) et AddPaymentInfo
- Interface d'administration avec 4 onglets : Réglages, Événements, Logs, Test
- Déduplication navigateur/serveur via `event_id` partagé
- Logs horodatés avec filtrage par événement et rétention configurable
