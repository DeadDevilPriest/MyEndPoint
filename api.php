<?php
    // ------------------------------------------------------------
    // 1) Variablen deklarieren (Startwerte)
    // ------------------------------------------------------------
    $fahrzeugName     = '';
    $fahrzeugType     = '';
    $fahrzeugLeistung = null;   // int|null
    $operationen      = [];     // z. B. ['get', 'update']
    
    $opGet    = false;
    $opPost   = false;
    $opUpdate = false;
    $opDelete = false;
    
    $erlaubteTypen       = ['auto', 'motorrad'];
    $erlaubteOperationen = ['get', 'post', 'update', 'delete'];
    
    $fehler     = [];
    $abgeschickt = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    
    // ------------------------------------------------------------
    // 2) Werte erfassen und den Variablen zuweisen
    // ------------------------------------------------------------
    if ($abgeschickt) {
        // Name (Text)
        $fahrzeugName = trim((string) ($_POST['fahrzeug-name'] ?? ''));
        if ($fahrzeugName === '') {
            $fehler[] = 'Bitte einen Namen eingeben.';
        }
    
        // Typ (nur "auto" oder "motorrad")
        $fahrzeugType = (string) ($_POST['fahrzeug-type'] ?? '');
        if (!in_array($fahrzeugType, $erlaubteTypen, true)) {
            $fehler[]     = 'Bitte einen gültigen Typ wählen.';
            $fahrzeugType = '';
        }
    
        // Leistung (Ganzzahl >= 0)
        $leistungRoh = $_POST['fahrzeug-leistung'] ?? '';
        $leistung    = filter_var($leistungRoh, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0],
        ]);
        if ($leistung === false) {
            $fehler[] = 'Leistung muss eine ganze Zahl ≥ 0 sein.';
        } else {
            $fahrzeugLeistung = $leistung;
        }
    
        // Operationen (radios -> Array)
        $eingabeOps  = $_POST['operation'] ?? [];
        $operationen = is_array($eingabeOps)
            ? array_values(array_intersect($erlaubteOperationen, $eingabeOps))
            : [];
    
        $opGet    = in_array('get', $operationen, true);
        $opPost   = in_array('post', $operationen, true);
        $opUpdate = in_array('update', $operationen, true);
        $opDelete = in_array('delete', $operationen, true);
    }
?>