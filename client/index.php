<?php
    $erlaubteOperationen = ['GET', 'POST', 'UPDATE', 'DELETE'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fahrzeug erfassen</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 520px; margin: 2rem auto; padding: 0 1rem; }
        fieldset { border: 1px solid #ccc; border-radius: 6px; padding: 1rem; }
        label { display: block; margin-top: .75rem; font-weight: 600; }
        input[type=text], input[type=number], select { width: 100%; padding: .4rem; box-sizing: border-box; }
        .radios label { display: inline-block; font-weight: normal; margin-right: 1rem; }
        button { margin-top: 1rem; padding: .5rem 1.2rem; }
    </style>
</head>
<body>

<h1>Fahrzeug erfassen</h1>

<form method="post" action="../MyEndPoint/api">
    <fieldset>
        <label for="fahrzeug-name">Name</label>
        <input type="text" id="fahrzeug-name" name="fahrzeug-name" required>

        <label for="fahrzeug-type">Typ</label>
        <select id="fahrzeug-type" name="fahrzeug-type" required>
            <option value="">-- bitte wählen --</option>
            <option value="auto">Auto</option>
            <option value="motorrad">Motorrad</option>
        </select>

        <label for="fahrzeug-leistung">Leistung (PS)</label>
        <input type="number" id="fahrzeug-leistung" name="fahrzeug-leistung"
               min="0" step="1" required>

        <label>Operation</label>
        <div class="radios">
            <?php foreach ($erlaubteOperationen as $op): ?>
                <label for="<?= $op ?>">
                    <input type="radio" id="<?= $op ?>" name="operation" value="<?= $op ?>" required>
                    <?= strtoupper($op) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <button type="submit">Absenden</button>
    </fieldset>
</form>
</body>
</html>