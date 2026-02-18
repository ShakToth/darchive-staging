<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>500 Fehler Debug - Miliz Uploads</title>
    <style>
        body { font-family: monospace; background: #1a1109; color: #f4e4bc; padding: 40px; line-height: 1.8; }
        .status { padding: 15px; margin: 10px 0; border-radius: 4px; }
        .ok { background: rgba(30, 255, 0, 0.2); border: 2px solid #1eff00; }
        .error { background: rgba(255, 0, 0, 0.2); border: 2px solid #ff0000; }
        .warning { background: rgba(255, 128, 0, 0.2); border: 2px solid #ff8000; }
        h1 { color: #d4af37; border-bottom: 2px solid #d4af37; padding-bottom: 10px; }
        pre { background: rgba(0,0,0,0.5); padding: 15px; border-radius: 4px; overflow-x: auto; }
        .test-image { max-width: 200px; border: 2px solid #8b5a2b; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>🔧 500 FEHLER DEBUG - MILIZ UPLOADS</h1>
    
    <div class="status warning">
        <strong>Dieser Tool hilft dir, 500 Fehler bei Miliz-Uploads zu finden!</strong>
    </div>
    
    <h2>1️⃣ ORDNER-STRUKTUR CHECK</h2>
    <?php
    $baseDir = __DIR__;
    $milizDir = $baseDir . '/miliz';
    $categories = ['befehle', 'steckbriefe', 'gesucht', 'protokolle', 'waffenkammer', 'intern'];
    
    if (is_dir($milizDir)) {
        echo "<div class='status ok'>✅ /miliz/ Ordner existiert</div>";
        
        foreach ($categories as $cat) {
            $catDir = $milizDir . '/' . $cat;
            if (is_dir($catDir)) {
                $files = array_diff(scandir($catDir), ['.', '..']);
                $fileCount = count($files);
                echo "<div class='status ok'>✅ /miliz/$cat/ existiert ($fileCount Dateien)</div>";
                
                // Liste erste 3 Dateien
                if ($fileCount > 0) {
                    $first3 = array_slice($files, 0, 3);
                    echo "<pre>Dateien: " . implode(', ', $first3);
                    if ($fileCount > 3) echo " ... (+" . ($fileCount - 3) . " weitere)";
                    echo "</pre>";
                }
            } else {
                echo "<div class='status error'>❌ /miliz/$cat/ fehlt!</div>";
            }
        }
    } else {
        echo "<div class='status error'>❌ /miliz/ Ordner fehlt komplett!</div>";
    }
    ?>
    
    <h2>2️⃣ .HTACCESS CHECK</h2>
    <?php
    $htaccessFile = $baseDir . '/.htaccess';
    if (file_exists($htaccessFile)) {
        $htaccess = file_get_contents($htaccessFile);
        echo "<div class='status ok'>✅ .htaccess gefunden</div>";
        
        // Prüfe ob Bild-Zugriff erlaubt ist
        if (stripos($htaccess, 'FilesMatch') !== false && 
            (stripos($htaccess, '.jpg') !== false || stripos($htaccess, 'Allow from all') !== false)) {
            echo "<div class='status ok'>✅ FilesMatch-Regel gefunden (Bild-Zugriff erlaubt)</div>";
        } else {
            echo "<div class='status warning'>⚠️ FilesMatch-Regel fehlt möglicherweise!</div>";
            echo "<div class='status warning'>";
            echo "<strong>Füge diese Regel zur .htaccess hinzu:</strong>";
            echo "<pre>" . htmlspecialchars('
<FilesMatch "\.(jpg|jpeg|png|gif|webp|pdf|txt|md|doc|docx)$">
    Order allow,deny
    Allow from all
</FilesMatch>

<FilesMatch "\.db$">
    Order allow,deny
    Deny from all
</FilesMatch>
') . "</pre>";
            echo "</div>";
        }
    } else {
        echo "<div class='status error'>❌ .htaccess nicht gefunden!</div>";
    }
    ?>
    
    <h2>3️⃣ DATEI-ZUGRIFF TEST</h2>
    <?php
    // Finde ein Testbild
    $testImagePath = null;
    $testImageWeb = null;
    
    foreach ($categories as $cat) {
        $catDir = $milizDir . '/' . $cat;
        if (is_dir($catDir)) {
            $files = array_diff(scandir($catDir), ['.', '..']);
            foreach ($files as $file) {
                if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file)) {
                    $testImagePath = $catDir . '/' . $file;
                    $testImageWeb = 'miliz/' . $cat . '/' . rawurlencode($file);
                    break 2;
                }
            }
        }
    }
    
    if ($testImagePath && file_exists($testImagePath)) {
        echo "<div class='status ok'>✅ Testbild gefunden: " . basename($testImagePath) . "</div>";
        echo "<div class='status'>";
        echo "<strong>Dateipfad:</strong> $testImagePath<br>";
        echo "<strong>Web-URL:</strong> $testImageWeb<br>";
        echo "<strong>Dateigröße:</strong> " . number_format(filesize($testImagePath) / 1024, 2) . " KB<br>";
        echo "</div>";
        
        // Versuche Bild anzuzeigen
        echo "<h3>🖼️ BILD-TEST:</h3>";
        echo "<div class='status'>";
        echo "<strong>Wenn du das Bild SIEHST → Alles OK!</strong><br>";
        echo "<strong>Wenn du ein ❌ SIEHST → 500 Fehler beim Laden!</strong><br><br>";
        echo "<img src='$testImageWeb' class='test-image' alt='Testbild' ";
        echo "onerror=\"this.parentElement.innerHTML='<div class=error>❌ FEHLER beim Laden!<br>URL: $testImageWeb<br><br>Mögliche Ursachen:<br>1. .htaccess blockiert Zugriff<br>2. Falsche Berechtigungen<br>3. mod_rewrite Problem</div>';\">";
        echo "</div>";
    } else {
        echo "<div class='status warning'>⚠️ Kein Testbild gefunden. Lade erst ein Bild in der Miliz hoch!</div>";
    }
    ?>
    
    <h2>4️⃣ PHP FEHLERLOG</h2>
    <?php
    $errorLog = ini_get('error_log');
    if ($errorLog && file_exists($errorLog)) {
        echo "<div class='status ok'>✅ Error Log gefunden: $errorLog</div>";
        echo "<div class='status warning'>";
        echo "<strong>Letzte 10 Zeilen:</strong>";
        $lines = file($errorLog);
        $last10 = array_slice($lines, -10);
        echo "<pre>" . htmlspecialchars(implode('', $last10)) . "</pre>";
        echo "</div>";
    } else {
        echo "<div class='status warning'>⚠️ Error Log nicht gefunden oder nicht zugänglich</div>";
        echo "<div class='status'>";
        echo "Prüfe Server-Logs manuell:<br>";
        echo "- Synology: /var/log/apache2/<br>";
        echo "- Standard: /var/log/httpd/error_log";
        echo "</div>";
    }
    ?>
    
    <h2>5️⃣ LÖSUNGSVORSCHLÄGE</h2>
    <div class="status warning">
        <h3>Häufigste Ursachen für 500 Fehler:</h3>
        <ol>
            <li><strong>.htaccess blockiert Zugriff</strong>
                <br>Lösung: FilesMatch-Regel hinzufügen (siehe oben)</li>
            
            <li><strong>Falsche Berechtigungen</strong>
                <br>Lösung: <code>chmod 644</code> auf alle Bilddateien</li>
            
            <li><strong>mod_rewrite Problem</strong>
                <br>Lösung: .htaccess temporär umbenennen und testen</li>
            
            <li><strong>PHP Syntax-Fehler</strong>
                <br>Lösung: Prüfe Error-Log (siehe oben)</li>
            
            <li><strong>Datei existiert nicht</strong>
                <br>Lösung: Prüfe ob Upload wirklich funktioniert hat</li>
        </ol>
    </div>
    
    <h2>6️⃣ MANUELLE TESTS</h2>
    <div class="status">
        <strong>1. Direkter Datei-Zugriff:</strong><br>
        Öffne Browser: <code>http://deine-site.de/miliz/waffenkammer/dein-bild.jpg</code><br>
        - Bild wird angezeigt? → .htaccess OK<br>
        - 500 Fehler? → .htaccess blockiert<br>
        - 404 Fehler? → Datei existiert nicht<br><br>
        
        <strong>2. Browser DevTools (F12):</strong><br>
        - Network Tab → Lade Seite neu<br>
        - Suche nach roten 500-Requests<br>
        - Rechtsklick → Copy → Copy as cURL<br>
        - Teste im Terminal<br><br>
        
        <strong>3. Temporär .htaccess deaktivieren:</strong><br>
        - Benenne .htaccess um zu .htaccess_backup<br>
        - Teste ob Bilder laden<br>
        - Falls ja: Problem ist in .htaccess<br>
        - Nicht vergessen: Zurückbenennen!
    </div>
    
    <br><hr><br>
    <p style="color: #888; font-size: 0.9rem;">
        Debug-Skript v5.0 | Kann nach erfolgreicher Fehlerbehebung gelöscht werden
    </p>
</body>
</html>
