<?php
// build.php - Générateur automatique du pack mg-MG depuis fr-FR (Joomla 6)

// ------------------------------------------------------------
// CONFIGURATION
// ------------------------------------------------------------
define('TEMP_DIR', __DIR__ . '/temp_build');
define('VERSION_FILE', __DIR__ . '/version.txt');
define('PKG_MANIFEST_TEMPLATE', __DIR__ . '/pkg_mg-MG.xml');
define('TRANSLATION_LIST', __DIR__ . '/translationlist_mg-MG.xml');
define('DETAILS_FILE', __DIR__ . '/details_mg-MG_pkg.xml');
define('GITHUB_REPO', 'ibesorongola/joomla-6-language-mg-mg'); // À MODIFIER
define('GITHUB_BRANCH', 'main');
define('AUTHOR_NAME', 'Ranaivomanantsoa Harilanto - Malagasy translation team'); // À MODIFIER
define('AUTHOR_EMAIL', 'votre.email@exemple.com'); // À MODIFIER
define('AUTHOR_URL', 'https://rdb.mg'); // À MODIFIER

// ------------------------------------------------------------
// TABLEAUX DE REMPLACEMENT (ajoutez ici vos motifs supplémentaires)
// ------------------------------------------------------------
$search = ['fr-FR', 'fr_FR', 'French', 'Fr-FR', 'Fr_FRLocalise', 'fr', 'français', 'french-fr', 'france', 'francophone'];          // motifs à rechercher (dans les chemins et le contenu)
$replace = ['mg-MG', 'mg_MG', 'Malagasy', 'Mg-MG', 'Mg_MGLocalise', 'mg', 'malagasy', 'malagasy-mg', 'madagasikara', 'miteny malagasy'];          // remplacements correspondants

// ------------------------------------------------------------
// FONCTIONS
// ------------------------------------------------------------

/**
 * Récupère les informations du dernier pack fr-FR (version et detailsurl)
 */
function getLatestFrInfo() {
    $translationListUrl = 'https://update.joomla.org/language/translationlist_6.xml';
    $xml = @simplexml_load_file($translationListUrl);
    if (!$xml) {
        throw new Exception('Impossible de charger le fichier de liste des langues Joomla 6');
    }
    foreach ($xml->extension as $ext) {
        if ((string)$ext['element'] === 'pkg_fr-FR') { // Attention : élément = pkg_fr-FR
            return [
                'version' => (string)$ext['version'],
                'detailsurl' => (string)$ext['detailsurl'],
            ];
        }
    }
    throw new Exception('Langue fr-FR non trouvée dans la liste');
}

/**
 * Récupère l'URL de téléchargement du zip fr-FR à partir du fichier de détail
 */
function getFrDownloadUrl($detailsUrl) {
    $detailsXml = @simplexml_load_file($detailsUrl);
    if (!$detailsXml) {
        throw new Exception('Impossible de charger le fichier de détail : ' . $detailsUrl);
    }
    // Le fichier ne contient normalement qu'un seul élément <update>
    $downloadUrl = (string)$detailsXml->update->downloads->downloadurl;
    if (empty($downloadUrl)) {
        // Sécurité : parcourir tous les updates
        foreach ($detailsXml->update as $update) {
            $url = (string)$update->downloads->downloadurl;
            if (!empty($url)) {
                return $url;
            }
        }
        throw new Exception('URL de téléchargement non trouvée dans le fichier de détail');
    }
    return $downloadUrl;
}

/**
 * Nettoie le répertoire temporaire
 */
function cleanTemp() {
    if (is_dir(TEMP_DIR)) {
        system('rm -rf ' . escapeshellarg(TEMP_DIR));
    }
    mkdir(TEMP_DIR);
}

/**
 * Transforme les fichiers (contenu) et renomme les dossiers/fichiers
 */
function transformFiles($sourceDir, $targetDir, $search, $replace) {
    $directory = new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::SELF_FIRST);

    foreach ($iterator as $item) {
        $relativePath = substr($item->getPathname(), strlen($sourceDir) + 1);
        $newRelativePath = str_replace($search, $replace, $relativePath);
        $targetPath = $targetDir . '/' . $newRelativePath;

        if ($item->isDir()) {
            if (!is_dir($targetPath)) {
                mkdir($targetPath, 0777, true);
            }
        } else {
            $targetDirPath = dirname($targetPath);
            if (!is_dir($targetDirPath)) {
                mkdir($targetDirPath, 0777, true);
            }
            $content = file_get_contents($item->getPathname());
            $content = str_replace($search, $replace, $content);
            file_put_contents($targetPath, $content);
        }
    }
}

/**
 * Corrige les métadonnées (auteur, email, URL) dans les fichiers XML des langues
 */
function fixMetadata($targetDir) {
    $metadataFiles = [
        $targetDir . '/language/mg-MG/install.xml',
        $targetDir . '/language/mg-MG/langmetadata.xml',
        $targetDir . '/administrator/language/mg-MG/install.xml',
        $targetDir . '/administrator/language/mg-MG/langmetadata.xml',
        $targetDir . '/api/language/mg-MG/install.xml',
        $targetDir . '/api/language/mg-MG/langmetadata.xml',
        // Le dossier installation n'est pas inclus
    ];

    foreach ($metadataFiles as $file) {
        if (file_exists($file)) {
            $xml = simplexml_load_file($file);
            if ($xml) {
                $xml->author = AUTHOR_NAME;
                $xml->authorEmail = AUTHOR_EMAIL;
                $xml->authorUrl = AUTHOR_URL;
                $xml->asXML($file);
            }
        }
    }
}

/**
 * Crée le zip du package mg-MG
 */
function createPackageZip($sourceDir, $zipPath) {
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception("Impossible de créer le zip $zipPath");
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
                                           RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($files as $file) {
        if (!$file->isDir()) {
            $relativePath = substr($file->getPathname(), strlen($sourceDir) + 1);
            $zip->addFile($file->getPathname(), $relativePath);
        }
    }
    $zip->close();
}

/**
 * Met à jour les fichiers XML de mise à jour
 */
function updateUpdateFiles($version, $zipUrl) {
    // Mise à jour de translationlist_mg-MG.xml
    $transList = simplexml_load_file(TRANSLATION_LIST);
    if ($transList === false) {
        $transList = new SimpleXMLElement('<extensionset name="Malagasy (mg-MG) Language Updates" description="Malagasy (mg-MG) Joomla core language pack updates"></extensionset>');
    }
    $found = false;
    foreach ($transList->extension as $ext) {
        if ((string)$ext['element'] === 'pkg_mg-MG') {
            $ext['version'] = $version;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $ext = $transList->addChild('extension');
        $ext['name'] = 'Malagasy (mg-MG) Language Package';
        $ext['element'] = 'pkg_mg-MG';
        $ext['type'] = 'package';
        $ext['version'] = $version;
        $ext['detailsurl'] = "https://raw.githubusercontent.com/" . GITHUB_REPO . "/" . GITHUB_BRANCH . "/details_mg-MG_pkg.xml";
    }
    $transList->asXML(TRANSLATION_LIST);

    // Mise à jour de details_mg-MG_pkg.xml
    $details = simplexml_load_file(DETAILS_FILE);
    if ($details === false) {
        $details = new SimpleXMLElement('<updates></updates>');
    }
    $found = false;
    foreach ($details->update as $update) {
        if ((string)$update->element === 'pkg_mg-MG') {
            $update->version = $version;
            $update->downloads->downloadurl = $zipUrl;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $update = $details->addChild('update');
        $update->addChild('name', 'Malagasy (mg-MG) Language Package');
        $update->addChild('description', 'Malagasy (mg-MG) Language Package for Joomla 6.x');
        $update->addChild('element', 'pkg_mg-MG');
        $update->addChild('type', 'package');
        $update->addChild('version', $version);
        $update->addChild('client', 'site');
        $downloads = $update->addChild('downloads');
        $downloadurl = $downloads->addChild('downloadurl', $zipUrl);
        $downloadurl->addAttribute('type', 'full');
        $downloadurl->addAttribute('format', 'zip');
        $update->addChild('infourl', "https://github.com/" . GITHUB_REPO . "/releases/tag/v$version");
        $target = $update->addChild('targetplatform');
        $target->addAttribute('name', 'joomla');
        $target->addAttribute('version', '6.[0-9]*');
        $update->addChild('php_minimum', '8.2');
    }
    $details->asXML(DETAILS_FILE);
}

// ------------------------------------------------------------
// EXÉCUTION PRINCIPALE
// ------------------------------------------------------------
try {
    echo "Démarrage de la construction...\n";

    $lastVersion = file_exists(VERSION_FILE) ? trim(file_get_contents(VERSION_FILE)) : '';
    echo "Dernière version traitée : " . ($lastVersion ?: 'aucune') . "\n";

    $frInfo = getLatestFrInfo();
    $latestFrVersion = $frInfo['version'];
    $frDetailsUrl = $frInfo['detailsurl'];
    echo "Dernière version fr-FR détectée : $latestFrVersion\n";

    if ($latestFrVersion === $lastVersion) {
        echo "Aucune nouvelle version. Arrêt.\n";
        exit(0);
    }

    echo "Nouvelle version détectée : $latestFrVersion. Génération en cours...\n";

    $frZipUrl = getFrDownloadUrl($frDetailsUrl);
    echo "URL de téléchargement du fr-FR : $frZipUrl\n";

    cleanTemp();

    echo "Téléchargement...\n";
    $frZipContent = file_get_contents($frZipUrl);
    file_put_contents(TEMP_DIR . '/fr.zip', $frZipContent);

    echo "Décompression...\n";
    $zip = new ZipArchive;
    $zip->open(TEMP_DIR . '/fr.zip');
    $zip->extractTo(TEMP_DIR . '/fr');
    $zip->close();

    echo "Transformation fr-FR -> mg-MG (fichiers et dossiers)...\n";
    transformFiles(TEMP_DIR . '/fr', TEMP_DIR . '/mg', $search, $replace);

    echo "Correction des métadonnées (auteur, email, URL)...\n";
    fixMetadata(TEMP_DIR . '/mg');

    echo "Génération du manifeste principal...\n";
    $manifestContent = file_get_contents(PKG_MANIFEST_TEMPLATE);
    $manifestContent = str_replace('__VERSION__', $latestFrVersion, $manifestContent);
    $manifestContent = str_replace('__DATE__', date('Y-m-d'), $manifestContent);
    file_put_contents(TEMP_DIR . '/mg/pkg_mg-MG.xml', $manifestContent);

    $zipName = "pkg_mg-MG_v{$latestFrVersion}.zip";
    $zipPath = __DIR__ . '/' . $zipName;
    echo "Création du package $zipName...\n";
    createPackageZip(TEMP_DIR . '/mg', $zipPath);

    $downloadUrl = "https://github.com/" . GITHUB_REPO . "/releases/download/v{$latestFrVersion}/$zipName";

    echo "Mise à jour des fichiers XML de mise à jour...\n";
    updateUpdateFiles($latestFrVersion, $downloadUrl);

    file_put_contents(VERSION_FILE, $latestFrVersion);

    cleanTemp();

    echo "Génération terminée avec succès !\n";
    echo "Package créé : $zipName\n";
    echo "Version : $latestFrVersion\n";

} catch (Exception $e) {
    echo "ERREUR : " . $e->getMessage() . "\n";
    exit(1);
}
