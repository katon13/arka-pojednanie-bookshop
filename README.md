# ARKA — księgarnia internetowa

Lekki sklep Wydawnictwa Katolickiego ARKA, oparty na sprawdzonym mechanizmie
księgarni PHP bez WordPressa i WooCommerce.

Ścieżka klienta:

> książka → dane i dostawa → Przelewy24 → potwierdzenie

Panel administratora pozwala zarządzać książkami, autorami, stronami
informacyjnymi, plikami ebooków, stanami magazynowymi, zamówieniami, zwrotami,
przesyłkami InPost, newsletterem i ustawieniami sklepu.

## Wymagania

- PHP 8.2+ z PDO, cURL, OpenSSL, fileinfo i GD,
- MySQL/MariaDB na OVH; SQLite do pracy lokalnej,
- osobne katalogi publiczne `public/` i `admin/`,
- HTTPS na produkcji.

## Uruchomienie lokalne w Laragonie

Na tym komputerze sklep działa przez Apache i PHP Laragona:

- sklep: `http://arka-pojednanie.test:8080`,
- panel: `http://arka-pojednanie.test:8080/admin`.

Konfiguracja VirtualHost znajduje się w
`config/laragon/arka-pojednanie.test.conf` i wskazuje bezpośrednio na
`X:\arkapojednanie`. Apache używa portu `8080`, ponieważ port `80` jest
zajęty przez usługę IIS. Nie trzeba uruchamiać osobnych serwerów `php -S`.

W gotowej kopii lokalnej `X:\arkapojednanie` plik `.env` jest już utworzony.
Nie zastępuj go plikiem `.env.example`. Przy świeżym wdrożeniu z paczki OVH
utwórz natomiast nowy `.env` z szablonu i wpisz dane właściwe dla serwera.

Przed pierwszym logowaniem ustaw w `.env`:

- losowy `APP_KEY`,
- `ADMIN_EMAIL`,
- mocne `ADMIN_PASSWORD_CHANGE_ME`.

Installer tworzy brakujące tabele i kolumny, ale nie kasuje istniejących danych.
Reset lokalnej bazy wykonuje wyłącznie:

```powershell
php scripts\reset_database.php --yes
```

## Przelewy24

Przelewy24 jest domyślnym operatorem płatności. Stripe pozostaje opcjonalnym,
wyłączonym modułem.

```env
PAYMENT_PRIMARY=przelewy24
P24_MODE=sandbox
P24_MERCHANT_ID=
P24_POS_ID=
P24_API_KEY=
P24_CRC=
```

Webhooki:

```text
POST {APP_URL}/api/webhooks/przelewy24
POST {APP_URL}/api/webhooks/przelewy24/refund
```

Powrót klienta ze strony operatora nie oznacza jeszcze zapłaty. Sklep oznacza
zamówienie jako opłacone dopiero po podpisanym powiadomieniu i dodatkowej
weryfikacji `transaction/verify` po API Przelewy24.

## Dane startowe

Nowa baza zawiera wyłącznie:

- konto administratora,
- ustawienia marki ARKA,
- książkę „Grzechy przeciwne nadziei” jako zapowiedź bez ceny,
- strony „O wydawnictwie”, „Idea znaku ARKA” i „Rekolekcje Pojednania”.

Nie zawiera danych, zamówień, klientów, logów ani plików poprzedniej księgarni.

## Przed uruchomieniem sprzedaży

1. Uzupełnij cenę, stan, dane wydania oraz ustaw status książki na
   `Przedsprzedaż` albo `Aktywna`.
2. Zweryfikuj regulamin i politykę prywatności z prawnikiem.
3. Uzupełnij koszty i dane InPost.
4. Wprowadź klucze P24 najpierw w trybie sandbox i wykonaj pełny test płatności.
5. Skonfiguruj pocztę SMTP oraz DNS SPF/DKIM/DMARC.
6. Na OVH ustaw MySQL, HTTPS, kopie zapasowe i zadanie cykliczne
   `php scripts/mail_queue_send.php`.
7. Uruchom `php scripts/final_predeploy_check.php`.

Sekrety i produkcyjny `.env` nie mogą trafić do repozytorium ani paczki
udostępnionej publicznie.
