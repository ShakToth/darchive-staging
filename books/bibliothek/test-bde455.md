---
title: Test
created: 2026-02-16 01:00:55
modified: 2026-02-16 01:00:55
author: Admin
---

<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Die Annalen des Webstuhls & Nachworte</title>
<style>
    /* Lade die alten Schriftarten von Google Fonts */
    @import url('https://fonts.googleapis.com/css2?family=IM+Fell+English:ital@0;1&family=Pirata+One&display=swap');

    /* Farbpalette für das alte Papier */
    :root {
        --bg-color: #f4ebd8; /* Pergament-Ton */
        --text-color: #2b2824; /* Dunkles Anthrazit statt hartem Schwarz */
        --accent-color: #611818; /* Getrocknetes Blutrot */
        --paper-texture: url('https://www.transparenttextures.com/patterns/aged-paper.png');
    }

    /* Allgemeine Einstellungen für den Bildschirm */
    html, body {
        margin: 0;
        padding: 0;
        width: 100%;
        height: 100%;
    }

    body {
        background-color: #1a1a1a; /* Dunkler Hintergrund für den Bildschirm */
        display: flex;
        justify-content: center;
        align-items: flex-start;
        padding-top: 2rem;
        padding-bottom: 2rem;
        font-family: 'IM Fell English', serif;
        color: var(--text-color);
        box-sizing: border-box;
    }

    /* Das eigentliche Buch-Blatt (Ansicht im Browser) */
    .book-page {
        background-color: var(--bg-color);
        background-image: var(--paper-texture);
        max-width: 550px;
        /* WICHTIG: Das Padding sorgt dafür, dass der Text nicht am Rand klebt */
        padding: 3rem 4rem; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.8);
        position: relative;
    }

    /* Haupt-Überschriften */
    h2 {
        font-family: 'Pirata One', serif;
        font-size: 2.8rem;
        color: var(--accent-color);
        text-align: center;
        margin-bottom: 2rem;
        margin-top: 0; /* Wichtig für den Anfang der Seite */
        font-weight: normal;
        border-bottom: 1px solid var(--accent-color);
        padding-bottom: 15px;
        line-height: 1.1;
    }

    /* Unter-Überschriften (I, II, III...) */
    h3 {
        font-size: 1.4rem;
        color: var(--accent-color);
        margin-top: 2.5rem;
        margin-bottom: 0.8rem;
        font-style: italic;
        font-weight: bold;
    }

    /* Fließtext-Design */
    p {
        font-size: 1.2rem;
        line-height: 1.6;
        text-align: justify; /* Blocksatz */
        margin-bottom: 1.2rem;
        hyphens: auto;
    }

    /* Initiale */
    h2 + p::first-letter {
        float: left;
        font-family: 'Pirata One', serif;
        font-size: 4.8rem;
        line-height: 0.8;
        padding-top: 4px;
        padding-right: 8px;
        padding-left: 2px;
        color: var(--accent-color);
    }

    /* Zierlinie */
    hr {
        border: 0;
        height: 1px;
        background-image: linear-gradient(to right, rgba(97, 24, 24, 0), rgba(97, 24, 24, 0.75), rgba(97, 24, 24, 0));
        margin: 3rem 0;
    }

    /* Hilfsklasse für Seitenumbrüche */
    .new-page {
        page-break-before: always;
        break-before: page;
    }


    /* =========================================
       HIER PASSIERT DIE MAGIE FÜR DEN DRUCK (PDF)
       ========================================= */
    @media print {
        /* 1. Zwingt den Drucker, KEINE weißen Ränder zu lassen */
        @page {
            size: A5;
            margin: 0mm; /* Das ist der Schlüssel! 0mm Rand. */
        }

        /* 2. Body und HTML müssen 100% der Seite füllen */
        html, body {
            width: 100%;
            height: 100%;
            margin: 0 !important;
            padding: 0 !important;
            background-color: var(--bg-color) !important;
            background-image: var(--paper-texture) !important;
            /* Stellt sicher, dass Farben exakt gedruckt werden */
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            display: block; /* Flexbox ausschalten für den Druck */
        }

        /* 3. Der Container für den Text */
        .book-page {
            box-shadow: none;
            max-width: 100% !important;
            width: 100% !important;
            margin: 0 !important;
            border-radius: 0;
            /* WICHTIG: Wir brauchen hier innen Abstand, damit der Text nicht 
               abgeschnitten wird, da der äußere Rand nun weg ist. */
            padding: 25mm 20mm 25mm 20mm !important; 
            box-sizing: border-box; /* Wichtig, damit Padding nicht die Breite sprengt */
        }
        
        /* Korrektur für den Seitenumbruch, damit der Text nicht zu hoch rutscht */
        .new-page {
             padding-top: 1rem;
        }
    }
</style>
</head>
<body>

    <div class="book-page">
        
        <h2>Die Annalen des Webstuhls: Eine Chronik des Zerfalls</h2>
        
        <p>Man sagt, die Wahrheit habe drei Häute. Die erste ist die Legende, die wir Kindern erzählen. Die zweite ist der Glaube, den wir wie einen Schild vor uns hertragen. Die dritte ist das nackte Grauen, das in den Zwischenräumen der Welt atmet.</p>

        <h3>I. Die Ur-Dissonanz: Das Lied der vergessenen Klinge</h3>
        <p>Am Anfang aller Schrecken stand ein einfaches Stück Eisen, verborgen in einer Kapelle, die bereits vor Äonen „betrunken vor Dunkelheit“ war. Jene vergessene Klinge war kein bloßes Werkzeug, sondern ein Sarg für Augenblicke, der jeden Tod gierig in sich aufsaugte. Es war die Tat eines namenlosen Elfen, die schließlich einen Riss im Gefüge der Existenz hinterließ. Das Metall wartete nur darauf, zum Kanal für etwas zu werden, das jenseits unserer Sphären nach Fleisch und Herrschaft hungerte: Yhrleh.</p>

        <h3>II. Das Gefäß der Leere: Die Gabe der Krähen</h3>
        <p>Jahrtausende mussten vergehen, bis die Klinge ihr wahres Ziel fand. Es war Elara, eine Frau, die kaum mehr als ein Gespenst in den Säumen der Gesellschaft war. Die Krähen – jene Archäologen der menschlichen Gleichgültigkeit – führten sie schließlich zu dem kalten Stahl. Als Elaras Leben endgültig in Scherben fiel, blieb in ihr nichts als eine absolute, verschlingende Leere. Ihr Freitod war der stumme Schrei, der das letzte Siegel brach und Yhrleh den Weg in unsere Welt ebnete. Doch in ihrem letzten Moment der Sorge um ihre Vögel webte Elara unbewusst eine winzige Dissonanz der Hoffnung in das dunkle Gefüge ein.</p>

        <h3>III. Das Reich der Makellosen: Mörderische Liebe</h3>
        <p>Yhrleh erhob sich als Dunkle Mondgöttin über ein Königreich der Dekadenz, in dem Schönheit zu einer scharfen Waffe wurde. Es war das Zeitalter einer mörderischen Liebe. Die Menschen verfingen sich in leerem Verlangen, während die Göttin sämtliche frommen Fesseln sprengte und die Seelen ihrer Anbeter wie reifes Korn erntete.</p>

        <h3>IV. Die Ära des Nachhalls: Das steinerne Herz</h3>
        <p>Heute, tausend Jahre später, ist von diesem Reich nur Staub geblieben. Zurück blieb eine verheerte Welt, besiedelt von Menschen, deren Herzen zu kalten Steinen geworden sind. Doch das Nachtgebet der Vergangenheit verhallt nicht ungehört. In der tiefsten Finsternis zieht sich plötzlich ein winziger Riss durch den Stein der Herzen. Es ist der ferne Nachhall von Elaras Hoffnung – eine zarte Liebe, die sich mühsam ihren Weg bricht und leise den Takt für ein neues Eden flüstert.</p>

        <hr>

        <h2 class="new-page">Nachwort der Chronistin Shaile</h2>
        
        <p>Ich habe das Muster der Zeit nun auf diesen Seiten niedergeschrieben, wie es meine Pflicht ist. Aber meine Hand zittert dabei fürchterlich. Ich fühle mich hier absolut nicht sicher. Ich bin auch keine normale Gästin in diesem Haus. Es ist schwer in Worte zu fassen, aber ich bin eine Gefangene meines Schicksals. Es ist fast so, als gäbe es eine unsichtbare Kette, die mein eigenes Blut mit diesen Aufzeichnungen verbindet und mich an diesen Ort gezogen hat. Ich musste einfach hierherkommen. Dennoch fühlte sich jede Meile, die ich mich diesem Haus am Rande des Nebels näherte, an wie ein Schritt in ein gewaltiges, offenes Maul.</p>

        <p>Eigentlich suche ich als Gläubige des Heiligen Lichts nach Reinheit. Doch hier riecht die Luft furchtbar nach modrigem Laub und altem Metall. Es ist ein ganz widerwärtiger Geruch, den ich sonst nur aus meinen schlimmsten Alpträumen kenne. Ich habe die Wirtin dieses Hauses heimlich beobachtet. Wenn die Welt schläft und die normalen Stunden der Anständigkeit vorbei sind, legt sie alle Masken ab.</p>

        <p>Gestern Nacht lag ich draußen bei diesen leuchtenden Moosen auf der Lauer. Im fahlen Licht sah ich sie. Sie bewegte sich auf eine Art... es war fast wie ein Tanz, aber es hatte überhaupt nichts von menschlicher Bescheidenheit. Da war eine zügellose, geradezu promiskuitiv wirkende Wildheit in ihr. Ihre Kleider waren kaum mehr als ein Hohn auf unsere Moral, und ihr unnatürlich roter Mund war zu einem Lächeln verzogen, das gefährlich und herrisch wirkte. In diesem Moment sah ich in ihr nicht mehr die Wirtin. Sie wirkte wie eine leibhaftige Manifestation der Dunklen Mondgöttin aus meinen Visionen. Wie eine Kreatur der mörderischen Liebe, die unbedingt angebetet werden will.</p>

        <p>Dann kam ein geflügelter Bote heran und brachte ihr ein Bündel, das in dreckige Lumpen gewickelt war. Als sie es sehr feierlich und streng in die Hände nahm, hörte ich es wieder. Es war jenes schreckliche, verführerische Summen der vergessenen Klinge. Ich spürte ein richtiges Vakuum, das förmlich nach mir rief.</p>

        <p>Ich wagte kaum noch zu atmen, doch dann geschah es. Sie blieb abrupt stehen. Ihr Kopf ruckte herum, und sie starrte mit ihren kastanienbraunen Augen direkt in mein Versteck im Gebüsch. In diesem Augenblick traf mich eine betäubende, eiskalte Angst. Mein Herzschlag drohte einfach auszusetzen. Es war kein normales Erschrecken. Ich fühlte mich wie ein hilfloses Beutetier, das in den Blick eines zornigen Gottes geraten ist. Sie wusste ganz genau, dass ich sie ansah. Und sie wusste, dass ich ihre Geschichte kenne.</p>

        <p>Sie sagte kein einziges Wort. Sie brachte das Bündel nur hinüber zu ihrem Wohnwagen und verstaute es dort unter den dicken Fellen. Sie hat mich nicht bestraft, jedenfalls nicht gleich und nicht laut. Doch ihr Blick war ein unmissverständliches Versprechen.</p>

        <p>Jetzt sitze ich hier, das Licht meiner kleinen Kerze flackert, und mir wird mit wachsendem Entsetzen etwas furchtbar klar. Das Steinerne Herz dieser Welt beginnt gerade zu zerbrechen. Das alte Lied aus den Legenden ist nicht verstummt. Es fängt jetzt erst richtig an, furchtbar laut zu werden.</p>


        <h2 class="new-page">Nachwort der Chronistin II</h2>
        
        <p>Es gibt eine Art von Stille, die noch viel lauter schreit als jeder Lärm. Genau so eine Stille herrschte heute Nacht im Inneren dieses Wohnwagens. Als würde der Raum selbst den Atem anhalten und nur darauf warten, dass ein Eindringling endlich einen Fehler macht.</p>

        <p>Ich hatte gewartet, bis der Mond ganz hoch stand und die Frau wieder von ihrer nächtlichen Unruhe getrieben im Wald verschwunden war. Mir war durch ihren durchdringenden Blick gestern natürlich klar geworden, dass die Klinge nicht dort drinnen war. Sie ist viel zu klug, um das Wichtigste so offensichtlich zu verstecken, wo sie doch weiß, dass ich sie beobachte. Aber ich musste unbedingt herausfinden, was sie dort aufbewahrt. Welcher Geist in einem solchen Schrein verehrt wird.</p>

        <p>Als Dienerin des Lichts sollte mir allein der Besitz eines Dietrichs die Schamesröte ins Gesicht treiben. Doch ich habe ihn benutzt, um das Schloss aufzubrechen. Die Tür schwang mit einem leisen, fast verächtlichen Quietschen auf.</p>

        <p>Der Gestank traf mich als Erstes. Es war nicht bloß der Geruch von getrockneten Kräutern und altem Holz, den ich erwartet hatte. Darunter lag etwas viel Schwereres. Ein süßlicher Geruch nach verwelkten Blüten und kaltem Rauch. Und dann war da, ganz subtil, dieser metallische Hauch von getrocknetem Blut. Er war nur schwach, aber ich konnte mir nicht einreden, dass es bloß Einbildung war.</p>

        <p>Der Innenraum war furchtbar eng und vollgestellt, aber von einer geradezu fanatischen Ordnung. Nirgendwo lag auch nur ein Staubkorn. Ich bewegte mich sehr vorsichtig, während das schwache Licht meiner abgeblendeten Laterne tanzende Schatten warf.</p>

        <p>Mein Blick fiel sofort auf einen kleinen Arbeitstisch. Dort lag kein Grimoire oder ein anderes Buch voller dunkler Zaubersprüche. Es stand dort ein profaner Webrahmen. Doch was dort eingespannt war, verschlug mir den Atem. Es war kein normales Garn. Es waren lange, schwarze Strähnen – viel zu dick für Rosshaar und zu fein für Wolle. Es war Menschenhaar. Gleich daneben lagen Spulen mit Sehnen, die dunkel eingefärbt waren. Das unvollendete Muster aus den schwarzen und dunkelroten Fäden zeigte eine spiralförmige Form. Es erinnerte mich furchtbar an einen Wirbelsturm oder ein gieriges Auge. Es war eine abscheuliche Perversion normalen häuslichen Fleißes.</p>

        <p>Weiter hinten im Regal standen dicht an dicht kleine Glasfläschchen. Sie waren nicht mit mystischen Runen versehen. Auf den vergilbten Etiketten waren einfache, fast kindliche Zeichnungen. Ein Fläschchen enthielt eine graue, trübe Flüssigkeit, und auf dem Etikett war ein geschlossenes Auge gezeichnet. Eine andere Flasche war mit einer tiefroten, dicken Substanz gefüllt und zeigte einen gesprungenen Kelch. Ein drittes Glas war bis auf feine weiße Asche leer und trug das Bild einer Feder. Das waren keine Tränke der Macht. Es waren vielmehr Destillate von Zuständen: Schlaf, Schmerz, vollkommene Vergessenheit. Dass sie diese Dinge so banal aufbewahrte, machte es nur noch viel verstörender.</p>

        <p>Und dann sah ich es. Auf einem grob gezimmerten Podest in der hintersten Ecke stand das eigentliche Herzstück dieses makabren Ortes.</p>

        <p>Es war gar keine Klinge. Es war ein Götze.</p>

        <p>Mein Atem stockte wieder, und diese betäubende Angst kroch mir erneut die Wirbelsäule hoch. Vor mir stand eine blasphemische Imitation einer Krähe. Kein Tier aus Fleisch und Blut, sondern ein Konstrukt. Ein Skelett aus gebogenen, dunklen Zweigen und kleinen Tierknochen bildete das Gerüst. Darüber waren unzählige schwarze, ölige Federn befestigt. Manche wirkten echt, andere sahen aus wie in Teer getauchte Stofffetzen. Sie waren mit grobem Garn und Draht festgebunden. Diese wilde, struppige Masse sah eher aus wie eine zerrupfte Vogelscheuche. Der Kopf war ein unförmiges Stoffbündel mit Schnüren, aus dem ein spitzer Knochen als Schnabel ragte.</p>

        <p>Aber das Schlimmste war sein Auge. Ein einzelnes, glühend rotes Auge. Vielleicht war es ein grob geschliffener Rubin oder einfach rotes Glas. Es glomm schwach im Dunkeln und starrte nicht einfach ins Leere. Es schien mich direkt anzusehen, mit einer bösartigen Intelligenz, die sich über das tote Material lustig zu machen schien. An einem Haken direkt über der Kreatur hing noch ein schweres eisernes Symbol. Es sah aus wie eine stilisierte Laterne oder ein kleiner Käfig. Er war leer, aber der Rost an den Gitterstäben sah verdächtig dunkel aus.</p>

        <p>Ich stand wie gelähmt da. Das war keine bloße Dekoration. Das hier war ein Altar. Die alten Geschichten von Elara und den Krähen schossen mir durch den Kopf. Diese Wirtin beschützt nicht nur die Klinge. Sie ist eine Priesterin! Und dieses Ding aus Draht, Knochen und Federn ist ihr Abbild des Boten. Es ist gebaut, um etwas anzulocken oder vielleicht auch zu bannen. Das leise Summen, das ich vorher der Klinge zugeschrieben hatte, schien nun aus diesem Götzen zu kommen. Es war wie ein vibrierender Hunger in der Luft.</p>

        <p>Ich wich Schritt für Schritt zurück. Ich war völlig unfähig, meinen Blick von dem roten Auge abzuwenden. Ich habe nichts gefunden, was ich den anderen als Beweis zeigen könnte. Keine Waffe, keinen Schatz. Aber ich habe etwas Schlimmeres gefunden: die Gewissheit.</p>

        <p>Die alte Chronik ist wahr. Und der Horror ist keine Erfindung aus alten Zeiten. Er wird genau hier, in diesem engen Raum, aus Haaren, Knochen und Asche gerade neu gewebt. Ich habe die Tür hinter mir geschlossen und das Schloss wieder einrasten lassen. Aber ich wusste, dass der Blick des roten Auges mir durch das massive Holz hindurch folgt. Ich war hier nicht mehr sicher. Ich bin nirgendwo mehr sicher.</p>

    </div>

</body>
</html>