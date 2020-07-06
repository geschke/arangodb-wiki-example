# Beispielcode Wiki mit ArangoDB aus dem PHP Magazin 5.20

## Vorbemerkungen

Bei dem Code aus diesem Repository handelt es sich um ein vollständiges, lauffähiges Wiki, jedoch bleibt es immer noch ein Beispiel. Es soll verdeutlichen, wie sich ArangoDB in der Praxis einsetzen lässt, um eine - in diesem Fall kleine - Anwendung zu realisieren. Es basiert auf dem Symfony Framework in Version 5.0, die im Zeitraum der Erstellung des dazu gehörigen Artikels die aktuelle Version darstellt.

Das Beispiel erhebt keinen Anspruch auf Vollständigkeit, so wurde etwa auf eine Prüfung der Eingaben an den meisten Stellen verzichtet, es beinhaltet keine Authentifizierung oder gar User-Verwaltung, ebenso wenig finden sich Übersetzungen oder Tests darin. In einer realen Anwendung wäre dies natürlich undenkbar!

Des Weiteren kennzeichnen "todo:..."-Hinweise die Stellen, an denen noch einige Funktionalität fehlt.

## Installation

Um das Beispiel zum Laufen zu bringen werden PHP ab Version 7.2.5 sowie eine ArangoDB-Installation benötigt. Der einfachste Weg zu ArangoDB führt über ein Docker-Image, aher wird im Folgenden die Installation von Docker vorausgesetzt.

In der folgenden Beschreibung wird die CLI-Variante von PHP eingesetzt, deren integrierter Web-Server zu Entwicklungs-Zwecken vollkommen ausreicht. Alle Beispiele wurden unter Linux mit einer aktuellen Ubuntu-Distribution getestet.

### ArangoDB

Wie bereits im Artikel beschrieben, wird ArangoDB in einem Docker-Container gestartet, wobei dank der Umgebungsvariable `ARANGODB_NO_AUTH=1` keine Authentifizierung notwendig ist. Das Verzeichnis `/srv/docker/arangodb` nimmt auf dem Host die ArangoDB-Datenbanken auf.

```
$ docker run -e ARANGO_NO_AUTH=1 -p 8529:8529 --name arangodb -d --restart=always -v /srv/docker/arangodb:/var/lib/arangodb3 arangodb
```

Nach erfolgreichem Start ist die ArangoDB-Admin-UI auf dem entsprechenden Host unter `http://<arangodb-host>:8529` erreichbar. Dort angekommen sind zunächst ein User, zum Beispiel `wikiuser`, sowie eine Datenbank, etwa `wikidb` anzulegen. Diese Angaben werden im weiteren Verlauf benötigt, damit die Beispiel-Anwendung mit ArangoDB kommunizieren kann. 

### Wiki

Wie bereits erwähnt, müssen PHP als CLI-Variante nebst einiger Module vorhanden sein, des Weiteren git und Composer.

Der Beispielcode kann direkt von GitHub herunter geladen werden, hier in ein Verzeichnis namens `wiki`:

```
$ git clone https://github.com/geschke/arangodb-wiki-example.git wiki

$ cd wiki
```

Danach werden die benötigten Libraries mit Composer installiert:

```
$ composer install
```

Im selben Verzeichnis befindet sich eine Datei `.env`, in dem sich bereits folgende Umgebungsvariablen zur Konfiguration der ArangoDB-Verbindung befinden:

```
ARANGODB_ENDPOINT='tcp://<arangodb-host>:8529'
ARANGODB_DATABASE='<DATENBANKNAME>'
ARANGODB_USER='<USERNAME>'
```

Diese können entweder geändert oder mit einer weiteren `.env.*`-Datei überschrieben werden, etwa `.env.local`. Als Verbindung ist der Hostname von ArangoDB einzutragen (ggf. auch `localhost`), darüber hinaus die zuvor in der ArangoDB-Admin-UI erstellten User- und Datenbank-Namen für das Wiki.

Der letzte Schritt besteht im Start des in PHP integrierten Web-Servers:

```
$ php -dvariables_order=EGPCS -S 127.0.0.1:8000 -t public
```

Als IP-Adresse wurde hier die Adresse von `localhost` genutzt. Falls der Host, auf dem PHP laufen soll, auch von weiteren Rechnern im lokalen Netz erreichbar sein soll, kann die Adresse entsprechend geändert werden.

Danach sollte ein Request auf `http://<hostname>:8000/` die Homepage des Beispiel-Wikis öffnen. Wenn dies funktioniert hat - Herzlichen Glückwunsch! Bevor es ans weitere Testen geht, sind nur noch zwei kleine Schritte zu erledigen, und zwar müssen **die Collections und der Graph angelegt** werden, analog zum Anlegen von Tabellen in einer relationalen Datenbank. Dazu dienen die folgenden zwei Requests:

`http://<hostname>:8000/db/create_wiki`

und

`http://<hostname>:8000/db/create_graph`.

Beides sollte mit einer Erfolgsmeldung quittiert werden. In der ArangoDB-Admin-UI kann der Erfolg ebenfalls geprüft werden, dort sollten die Document-Collection namens `wiki`, die Edge-Collection `pageEdge` und der Graph `pageGraph` innerhalb der zuvor angelegten Datenbank (`wikidb`) vorhanden sein.

Nun können Seiten angelegt werden, mit einem Klick auf **Wiki** gelangt man in das Formular zum Anlegen der Haupt-Seite, die immer die Kurzbezeichnung *main* trägt.

Wiki-Seiten werden verlinkt bzw. angelegt, indem im Text **[[Slug]]** geschrieben wird, dabei ist *Slug* eine Kurzbezeichnung für die Seite (ohne Leerzeichen!), somit letztlich Bestandteil der URL. Zur Formatierung wird Markdown unterstützt.

## Fragen? Anmerkungen? Kommentare?

An dieser Stelle bleibt mir nur noch, viel Spaß beim Stöbern und Ausprobieren zu wünschen. Für Fragen, Anmerkungen oder Kommentare stehe ich gerne bereit, entweder per Mail oder über das Feedback-Formular auf meiner Seite [kuerbis.org](https://www.kuerbis.org/).
