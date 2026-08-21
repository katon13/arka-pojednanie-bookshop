# Baza sklepu

Aktualny schemat jest tworzony przez `app/Services/Database/Installer.php`.

Gotowe kopie do przeniesienia na serwer generuje:

```text
php scripts/export_server_database.php --source=storage/database.sqlite
```

Skrypt tworzy w `storage/exports`:

- spójną kopię SQLite bez tabel testowych i migracyjnych,
- kompletny import dla MySQL/MariaDB wraz z danymi i indeksami,
- manifest z liczbą rekordów oraz sumami SHA-256.

Eksport zawiera dane klientów i hash konta administratora. Nie należy umieszczać go
w publicznym katalogu serwera ani w repozytorium.
