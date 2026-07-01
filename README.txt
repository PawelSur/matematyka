# Quiz matematyka - wersja z kontami

## Wrzucenie na serwer

1. Wgraj wszystkie pliki z tej paczki do jednego katalogu na serwerze, np.:
   `/quiz-matematyka/`

2. Struktura ma wyglądać tak:

   - index.html
   - api.php
   - data/
     - .htaccess

3. Ustaw zapisywanie do katalogu `data`:

   chmod 775 data

   Jeśli serwer nadal nie może zapisywać:

   chmod 777 data

4. Otwórz w przeglądarce:

   https://twojadomena.pl/quiz-matematyka/index.html

## Jak działa konto

- użytkownik tworzy konto przez email + hasło,
- hasło jest hashowane przez `password_hash()`,
- postęp jest trzymany w plikach JSON w katalogu `data`,
- po zalogowaniu na innym urządzeniu quiz pobiera i scala postęp z serwera,
- synchronizowane są:
  - poprawne odpowiedzi,
  - błędne odpowiedzi,
  - liczba poprawnych/błędnych,
  - lista błędnych pytań do powtórki,
  - pytania nieodpowiedziane.

## Wymagania

- PHP 7.4+ albo PHP 8.x
- włączone sesje PHP
- możliwość zapisu do katalogu `data`

## Ważne

To jest prosta wersja kont pod naukę, nie panel bankowy. Do prywatnego quizu wystarczy.
Na publiczny serwer warto używać HTTPS.
