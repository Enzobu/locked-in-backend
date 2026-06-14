# Production Readiness — LockedIn (réservation de casiers)

> Audit du **14/06/2026**. Objectif : si on met en prod **demain**, qu'est-ce qui manque côté **front (Flutter)** et **back (Symfony)** pour être réellement prod-ready et couvrir tous les cas d'usage d'un système de réservation de casier. Périmètre : uniquement du **réalisable**.
>
> **Branches auditées : `origin/dev` des deux repos** (back `locked-in-backend@f8e51a5`, front `locked-in-mobile@5d3d8e9` / PR #100). ⚠️ Le front local checkouté était périmé de ~245 commits — cet audit se base sur le vrai `origin/dev`, beaucoup plus avancé que prévu.
>
> Priorisation : **P0** = bloquant (prod casse / faille / cas métier non géré), **P1** = important, **P2** = confort/polish.

---

## 1. État des lieux

### Backend (`locked-in-backend`, Symfony + API Platform) — avancé
Déjà en place :
- Modèle complet : `Customer`, `User` (staff), `Company`, `LockerBay`, `Locker`, `Specification`, `Reservation`, `Address`, `LockerAction`, `LockerEvent` (+ enums de statut).
- Auth **JWT** (Lexik, TTL 24h, clés RSA), providers séparés client/admin, soft-delete + `ActiveUserChecker`.
- Inscription (`POST /register`), `GET /customers/me`, `PATCH /customers`.
- **Paiement Stripe** : PaymentIntent + clôture de résa avec calcul de **dépassement** (grâce + surcharge) — `ReservationPricingService`, `ApiStripePaymentController`, webhook signé.
- **Gateway hardware** casier via WebSocket/Redis (`/api/locker/{id}/open`), audit `LockerAction`/`LockerEvent`.
- Back-office Twig (dashboard, lockers, companies, customers, users).

### Frontend (`locked-in-mobile`, Flutter clean architecture) — quasi-complet
Bien plus abouti qu'attendu. Déjà en place :
- **Wiring API réel** : tous les providers injectent les `Api*Datasource` via Dio (`dioClientProvider`). Les `mock_*` existent mais ne sont **pas** utilisés. Pas de toggle mock/prod.
- **Auth complète** : login / register / splash, restauration de session au lancement (`/customers/me`), logout qui purge le token, interceptor JWT qui injecte le Bearer et **purge le token sur 401**.
- **Routing** `go_router`, navigation par shell + bottom bar.
- **Tunnel de réservation complet** : sélection date/durée (clamp min/max) → résumé → **paiement Stripe réel** (`flutter_stripe` PaymentSheet) → succès → ouverture du casier (overlay). Gestion d'erreur de paiement.
- **Mes réservations** : liste, détail, annulation (confirmation), pull-to-refresh.
- **Profil** : affichage + édition.
- **Carte** géolocalisée (`flutter_map` + `geolocator`).
- **i18n 4 langues** (fr/en/de/it, `.arb`).
- **États** erreur/vide/skeleton, `ApiException` (timeout, no-internet, 4xx/5xx).
- **47 fichiers de test** + **CI GitHub Actions** (analyze, format, test, build APK).

**Conclusion** : on est bien plus proche d'un MVP que ne le laissait croire la branche locale. Le gros du chemin restant = **durcissement sécurité/exploitation** + **quelques règles métier critiques côté back** + branchement de la **config de prod**. La majorité des écrans et du flux fonctionnent déjà de bout en bout contre le backend de test.

---

## 2. Cas d'usage d'un système de réservation — couverture réelle

| # | Cas d'usage | Back | Front | Manque |
|---|-------------|------|-------|--------|
| 1 | Découvrir les casiers (liste/carte/proche) | ✅ | ✅ | — |
| 2 | S'inscrire / se connecter | ✅ | ✅ | — |
| 3 | Mot de passe oublié / reset | ❌ | ❌ | flow complet back + front |
| 4 | Vérifier dispo sur un créneau | ❌ | ⚠️ | **endpoint dispo + check anti double-booking** |
| 5 | Réserver (date/heure/durée) | ⚠️ | ✅ | check chevauchement + durée min/max côté back |
| 6 | Payer | ✅ | ✅ | clé Stripe **live** en prod |
| 7 | Recevoir une confirmation | ❌ | ⚠️ écran succès | **email/push de confirmation** |
| 8 | Ouvrir le casier | ✅ | ✅ | — |
| 9 | Voir mes réservations | ✅ | ✅ | — |
| 10 | Prolonger une réservation | ❌ | ❌ | endpoint + UI |
| 11 | Gérer le dépassement (overtime) | ✅ | ⚠️ | UI + notif de dépassement |
| 12 | Annuler / rembourser | ⚠️ DELETE brut | ✅ annulation | **règles d'annulation + remboursement Stripe** |
| 13 | Clôturer / libérer le casier | ✅ (`/close`) | ✅ | — |
| 14 | Expiration auto d'une résa non honorée | ❌ | — | **job cron** (statut `EXPIRED` jamais déclenché) |
| 15 | Casier hors-service / offline | ⚠️ statut existe | ⚠️ | exclure de la résa + UX |
| 16 | Gérer profil / adresses | ✅ | ✅ | changer MdP (non câblé), champ téléphone |
| 17 | Support / litige / contact | ❌ | ❌ | au minimum un lien contact |

Légende : ✅ fait · ⚠️ partiel/fragile · ❌ absent.

---

## 3. P0 — Bloquants avant prod

### Back — métier
- **[P0] Anti double-booking (#4/#5).** `src/State/ReservationPostProcessor.php` assigne le client puis persiste **sans vérifier qu'aucune résa ne chevauche** le créneau sur le même casier. Deux clients peuvent réserver le même casier au même moment.
  - À faire : requête de chevauchement `starts_at < :endsAt AND ends_at > :startsAt AND status IN (PENDING,CONFIRMED,ACTIVE)` sur le `locker_id` → `409 Conflict`. + **verrou transactionnel** (`SELECT … FOR UPDATE` ou contrainte d'exclusion) contre la race condition concurrente.
- **[P0] Respecter `minDuration`/`maxDuration` du `LockerBay`.** Champs présents, **vérifiés nulle part** côté back (le front clamp déjà, mais le back ne doit pas faire confiance au client).
- **[P0] Expiration automatique (#14).** Le statut `EXPIRED` existe mais n'est **jamais** positionné → une `PENDING` non payée bloque le casier indéfiniment. **Commande cron** (Symfony Scheduler/Messenger) qui expire les `PENDING` trop vieilles et libère le casier.
- **[P0] Idempotence du paiement.** `POST /api/payments/intents` n'a pas de clé d'idempotence → double-clic = deux PaymentIntents / deux résas. Ajouter une idempotency key Stripe + dédup résa.

### Back — sécurité / config
- **[P0] CORS.** `config/packages/nelmio_cors.yaml` autorise `*` (origines/headers/méthodes). Restreindre aux origines réelles.
- **[P0] Secrets & env de prod.** `APP_ENV=prod`, `APP_SECRET`, clés JWT, `STRIPE_SECRET_KEY`/`STRIPE_WEBHOOK_SECRET`, `DATABASE_URL` via secrets (jamais en clair dans un `.env` committé). Vérifier le `.env` du repo.
- **[P0] Rate limiting auth.** Aucun sur `/api/login` ni `/register` → brute-force. Ajouter `symfony/rate-limiter`.
- **[P0] Webhook Stripe robuste.** Signature vérifiée (bien) mais réponse `200` même si la résa est introuvable → risque d'incohérence paiement/résa. Whitelister les event types, logger, réconcilier le statut.

### Front — config de prod (sinon l'app pointe sur le backend de test)
- **[P0] Stockage du token non sécurisé.** `lib/core/network/token_storage.dart` stocke le JWT dans **`shared_preferences` (clair)**. Migrer vers **`flutter_secure_storage`** (Keychain/Keystore).
- **[P0] URL d'API de prod.** `lib/core/network/api_constants.dart` a pour défaut `https://openinnov-backend.enzo-palermo.com` (backend de test). Builder avec `--dart-define=API_BASE_URL=https://<prod>`.
- **[P0] Clé Stripe live.** `api_constants.dart` embarque une **clé `pk_test_…` en dur**. Builder avec `--dart-define=STRIPE_PUBLISHABLE_KEY=pk_live_…` (et ne jamais committer une clé live).
- **[P0] Reste de test à retirer.** `reservation_detail_screen.dart:32` : `bool get _isActive => true; // TODO restore proper condition` → toujours « actif », masque le vrai statut de la résa. À rétablir avant prod.

---

## 4. P1 — Important

### Back
- **[P1] Mot de passe oublié (#3).** `POST /password/forgot` (token à durée limitée) + `POST /password/reset`. Nécessite l'email (ci-dessous).
- **[P1] Envoi d'emails.** `MAILER_DSN=null://null` → **aucun email** (confirmation inscription/réservation, reçu, alerte dépassement, reset MdP). L'infra Messenger `async` mail est câblée mais **inutilisée**.
- **[P1] Vérification d'email à l'inscription** (compte actif immédiatement aujourd'hui).
- **[P1] Endpoint changement de mot de passe.** Le front a déjà le bouton mais `profile_screen.dart:505` = `// TODO password change API` faute d'endpoint back. Exposer `POST /customers/me/password`.
- **[P1] Validation au niveau entité.** Validation **manuelle dans les contrôleurs**, validator Symfony désactivé. Ajouter contraintes (`NotBlank`, `Email`, `Length`, `UniqueEntity`, âge mini via `birthDate`).
- **[P1] Annulation + remboursement (#12).** Le `DELETE` API Platform supprime la résa sans règle ni remboursement. Définir délai gratuit, Stripe Refund, statut `CANCELLED` (pas de delete).
- **[P1] Prolongation (#10).** Endpoint pour étendre `ends_at` (re-check dispo + paiement complémentaire).
- **[P1] Exclure les casiers `OUT_OF_ORDER`/`OFFLINE`** des résultats réservables et de la création.
- **[P1] Tests back.** `tests/` ne contient que `bootstrap.php` → **0 test** (contraste avec les 47 du front). Couvrir création de résa (chevauchement rejeté), pricing/overtime, auth, webhook.
- **[P1] Reporting d'erreurs + logs métier.** Monolog configuré (JSON prod) mais aucun log métier ; ajouter Sentry + logs paiement/réservation.
- **[P1] Healthcheck** `/health` (DB, Redis, Stripe) pour le monitoring.

### Front
- **[P1] Pas de refresh token.** Sur 401 l'interceptor purge tout → déconnexion sèche (TTL JWT 24h). Acceptable en MVP mais prévoir refresh pour l'UX.
- **[P1] Profil sur `/customers/{id}` au lieu de `/me`** (`api_profile_datasource.dart:18`, TODO) — aligner quand le back expose le PATCH `/me`.
- **[P1] Champ téléphone** non envoyé (`api_profile_datasource.dart:31`, TODO) — à activer quand le back ajoute le champ.

### Transverse / infra
- **[P1] CI back** (le front a déjà sa CI ; ajouter lint + tests PHP côté back).
- **[P1] Migrations rejouables.** Une seule migration (`Version20260403095459`) qui suppose un schéma initial déjà présent. Régénérer un jeu propre pour provisionner une base vierge en prod.
- **[P1] Build prod front paramétré** (script/CI qui passe `--dart-define` URL + clés, signe l'APK/IPA).
- **[P1] Doc API** : figer l'OpenAPI généré par API Platform pour aligner front/back (collection Postman déjà présente).

---

## 5. P2 — Confort / polish

### Back
- **[P2]** Pagination & filtres normalisés (lockers, réservations).
- **[P2]** Audit log applicatif (au-delà des `LockerEvent` matériels).
- **[P2]** S'assurer que toutes les requêtes filtrent `is_deleted = true`.
- **[P2]** Retry/réconciliation des paiements off-session échoués (overtime).
- **[P2]** Sync périodique du statut matériel (offline si `lastSeenAt` ancien).

### Front
- **[P2] Notifications push** (`notification_provider.dart` = simple flag en `shared_preferences`, pas de FCM). Rappel fin de créneau, dépassement, casier ouvert.
- **[P2] Détection de connectivité / offline** (pas de `connectivity_plus`).
- **[P2] Build flavors** dev/staging/prod (au lieu de `--dart-define` manuels).
- **[P2] Lien support/contact (#17)**, accessibilité (`Semantics`), crash reporting (Sentry/Firebase).

---

## 6. Checklist « go / no-go » minimale (essentiellement les P0)

**Back**
- [ ] Check anti-chevauchement + verrou transactionnel à la création de résa
- [ ] Respect `minDuration`/`maxDuration` côté serveur
- [ ] Cron d'expiration des résa non payées
- [ ] Idempotence paiement
- [ ] CORS restreint + secrets hors repo + `APP_ENV=prod`
- [ ] Rate limiting login/register
- [ ] Webhook Stripe : réconciliation + whitelist d'events
- [ ] Emails de confirmation (au moins inscription + réservation)

**Front**
- [ ] Token en `flutter_secure_storage`
- [ ] `--dart-define` URL d'API de prod
- [ ] `--dart-define` clé Stripe **live**
- [ ] Retirer le `_isActive => true` de test (`reservation_detail_screen.dart`)

**Transverse**
- [ ] Jeu de migrations rejouable sur base vierge
- [ ] Tests back sur les chemins critiques (résa, paiement, auth)
- [ ] Healthcheck + reporting d'erreurs (Sentry)

---

## 7b. Implémenté dans la branche `feature/prod-readiness-business-logic`

Logique métier back livrée (hors CORS/secrets/emails, gardés pour la V2), chaque
palier avec ses tests :

| Item | Statut | Commit / fichiers clés |
|------|--------|------------------------|
| Anti double-booking + verrou + durée min/max + casier HS | ✅ | `ReservationAvailabilityChecker`, `ReservationRepository::findOverlapping`, câblé dans `ReservationPostProcessor` + `/payments/intents` |
| Annulation propre + remboursement + libération casier | ✅ | `ReservationLifecycleService`, `ReservationPatchProcessor`, colonnes `cancelled_at/refund_id/refund_status` (+ migration) |
| Statut casier synchronisé (OCCUPIED/RESERVED/AVAILABLE) | ✅ | `LockerStateService` |
| Expiration auto des holds non payés | ✅ | commande `app:reservations:expire` |
| Webhook Stripe robuste (confirm/échec, idempotent, signature) | ✅ | `ApiStripePaymentController::webhook` via lifecycle |
| Idempotence paiement (réutilisation hold + Idempotency-Key) | ✅ | `findReusablePending`, `StripeClient` |
| Changement de mot de passe | ✅ | `POST /api/customers/me/password` |
| Validation entité + tests unitaires/intégration | ✅ | contraintes `Assert`, `tests/Unit/*`, `tests/Api/*` |

Tests : **14 tests PHPUnit** (services purs) + **58 assertions d'intégration HTTP**
(`tests/Api/`, voir `tests/Api/README.md`).

**Reste hors périmètre de cette branche** (volontairement) : restriction CORS,
secrets de prod, emails + mot de passe oublié (V2), prolongation de réservation
(#10), filtrage des casiers HS dans le *listing* (la réservation est déjà
bloquée), step PHPUnit dans la CI. Le chemin de remboursement Stripe est codé
mais non rejouable en local sans clé `sk_test`.

## 7. Risques principaux résumés

1. **Double-booking** — défaut métier central, non géré côté back (P0).
2. **Casiers jamais libérés** — pas d'expiration auto (P0 back).
3. **Config de prod non branchée côté front** — token non sécurisé, URL + clé Stripe encore en *test* (P0 front).
4. **CORS `*` + pas de rate limiting** — surface d'attaque (P0 back).
5. **0 test / 0 email / 0 monitoring côté back** — exploitation à l'aveugle (P1).
6. **Reste de debug** (`_isActive => true`) qui masque le statut réel des réservations (P0 front).
