<!DOCTYPE html>
<html>
<head>
  <title>Comenzi utile pentru Mautic</title>

<style>
  input::placeholder, textarea::placeholder {text-align:center;}
  input[type="password"], textarea {width:80%; padding:10px; border:1px solid gray; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.1);}

  div#outputdiv {width:80%; border:1px solid blue; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.1);}
  pre#output {display:block; margin:10px;}
  #cod-rezultat {font-size:12px; font-weight:bold;}

  input[type="submit"] {background-color:#4CAF50; color:white; padding:14px 20px; margin:8px 0; border:none; cursor:pointer; width:100%; opacity:0.9; border-radius:10px;}
  input[type="submit"]:hover {opacity:1;}
  input[type="submit"]:disabled {background-color:#f0f0f0;cursor:not-allowed;}
  
  input[type="radio"] {display:none;}
  div.command-container {display:inline-block; padding:10px; background-color:#f0f0f0; margin:5px; border-radius:10px; cursor:pointer; text-align:center}
  div.command-container label[name="description"], #commanddescription {font-weight:bold; cursor:pointer;}
  div.command-container label[name="command"] {font-size:0.9em;cursor:pointer; display:none;}
  .div-selectat {background-color:#d0d0d0 !important; box-shadow:0 0 10px rgba(0,0,0,0.1);}

  input[type="range"] {appearance:none;-webkit-appearance:none;
    width:80%; max-width:200px; height:15px; border-radius:5px; background:#d3d3d3; outline:none; opacity:0.7;
    -webkit-transition:1.5s; transition:opacity 1.5s;}
  input[type="range"]::-webkit-slider-thumb {-webkit-appearance:none; appearance:none;
    width:25px;height:25px;border-radius:50%; background:#4CAF50; cursor:pointer;}
  input[type="range"]::-moz-range-thumb {width:25px; height:25px; border-radius:50%; background:#4CAF50; cursor:pointer;}
</style>

</head>
<body>

<?php
session_start();

// Generarea token-ului CSRF (Cross-Site Request Forgery token)
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

require '/var/mautic-crons/mautic.php';

$shouldReload = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $user_token = $_POST['csrf_token'];
  if (empty($user_token) || !hash_equals($_SESSION['csrf_token'], $user_token)) {
    // Token-ul CSRF nu există sau nu se potrivește
    die('Token-ul CSRF este invalid. Reimprospateaza pagina (Reload / Refresh / F5)');
  } else {
    // Procesarea formularului
    $parola = $_POST['parola'];
    
    // Verificați dacă parola introdusă corespunde cu cea salvată
    if ($parola == $mautic_comenzi_secret_key) {
      $parolaCorecta = true;
      $durataMaxima = 60;
        
      if (isset($_POST['commandaleasa'])) {

        $commandAleasa = $_POST['commandaleasa'];
        $durataMaxima = (int)$_POST['durataslider']; // este "60"
        set_time_limit($durataMaxima + 20);

        // Deschideți procesul
        $descriptorspec = array(
          0 => array("pipe", "r"),  // stdin
          1 => array("pipe", "w"),  // stdout
          2 => array("pipe", "w")   // stderr
        );
        $process = proc_open($commandAleasa, $descriptorspec, $pipes);

        if (is_resource($process)) {
          // Setează timpul de început
          $timpStart = microtime(true);

          while (true) {
            // Verificați dacă procesul încă rulează
            $status = proc_get_status($process);

            if (!$status['running']) {
              // Dacă procesul s-a terminat, închideți-l și ieșiți din buclă
              $output = stream_get_contents($pipes[1]);
              fclose($pipes[1]);
              fclose($pipes[2]);
              $codRezultat = proc_close($process); // Obține codul de ieșire
              break;
            } else if ((microtime(true) - $timpStart) > $durataMaxima) {
              // Dacă procesul depășește timpul maxim, închideți-l
              proc_terminate($process);
              $output = "Comanda a fost întreruptă deoarece s-a depășit timpul specificat de " . $durataMaxima . " secunde.";
              $codRezultat = -2; // Setează un cod de eroare personalizat pentru timeout
              break;
            }
            // Așteptați 0.5s înainte de a verifica din nou
            usleep(500000);
          }
        }
        
      }
      $phpConsolePath = 'php '.$dosar_instalare_mautic.'bin/console ';
      $comenzi = [
        [
          'description' => file_exists($dosar_instalare_cron.'DO-NOT-RUN') ? 'Activeaza CronJob-urile' : 'Dezactiveaza CronJob-urile',
          'command' => 'bash '.$dosar_instalare_cron.'schimbaCronJobs.sh'
        ],
        [
          "description" => "Lista comenzilor Consolei Mautic",
          "command" => $phpConsolePath.'list'
        ],
        [
          "description" => 'Restabileşte permisiunile dosarului Mautic',
          "command" => 'bash /usr/local/bin/reset-permisiuni-mautic.sh'
        ],
        [
          "description" => "Şterge cache-ul",
          "command" => $phpConsolePath.'cache:clear'
        ],
        [
          "description" => "Crează acum o copie de rezervă a mautic (baza de date şi dosar Mautic)",
          "command" => 'bash '.$dosar_instalare_cron.'cron-backup.sh'
        ],
        [
          "description" => "Actualizează toate segmentele",
          "command" => $phpConsolePath.'mautic:segments:update'
        ],
        [
          "description" => "Actualizează toate campaniile",
          "command" => $phpConsolePath.'mautic:campaigns:update'
        ],
        [
          "description" => "Proceseaza toate campaniile",
          "command" => $phpConsolePath.'mautic:campaigns:trigger'
        ],
        [
          "description" => "Trimite emailurile",
          "command" => $phpConsolePath.'mautic:emails:send'
        ],
        [
          "description" => "Trimite newsletterele",
          "command" => $phpConsolePath.'mautic:broadcasts:send'
        ],
        [
          "description" => "Trimite SMS-urile",
          "command" => $phpConsolePath.'mautic:messages:send'
        ],
        [
          "description" => "Proceseaza webhook-urile",
          "command" => $phpConsolePath.'mautic:webhooks:process'
        ],
        [
          "description" => "Proceseaza rapoartele programate",
          "command" => $phpConsolePath.'mautic:reports:scheduler'
        ],
        [
          "description" => "Actualizeaza plugin-urile",
          "command" => $phpConsolePath.'mautic:plugins:update'
        ],
        [
          "description" => "Importa 600 contacte",
          "command" => $phpConsolePath.'mautic:import --limit=600'
        ],
        [
          "description" => "Arata info mai vechi de 90 de zile ce pot fi sterse",
          "command" => $phpConsolePath.'mautic:maintenance:cleanup --no-interaction --days-old=90 --dry-run'
        ],
        [
          "description" => "Sterge info mai vechi de 90 de zile",
          "command" => $phpConsolePath.'mautic:maintenance:cleanup --no-interaction --days-old=90'
        ],
        [
          "description" => "Deduplicarea contactelor",
          "command" => $phpConsolePath.'mautic:contacts:deduplicate'
        ],
        [
          "description" => "Şterge IP-urile nefolosite",
          "command" => $phpConsolePath.'mautic:unusedip:delete'
        ],
        [
          "description" => "Actualizează baza de date MaxMind",
          "command" => $phpConsolePath.'mautic:iplookup:download'
        ],
        [
          "description" => "Vezi starea migrărilor",
          "command" => $phpConsolePath.'doctrine:migrations:status'
        ],
        [
          "description" => "Validează starea migrărilor",
          "command" => $phpConsolePath.'doctrine:schema:validate'
        ],
        [
          "description" => "Afişează comenzile SQL pentru a actualiza baza de date",
          "command" => $phpConsolePath.'doctrine:schema:update --dump-sql'
        ],
        [
          "description" => "Resetează statistica emailurilor de la Webinarii",
          "command" => $phpConsolePath."doctrine:query:sql \"UPDATE emails SET read_count = 0, sent_count = 0, variant_sent_count = 0, variant_read_count = 0 WHERE id IN (SELECT e.id FROM emails e JOIN categories c ON e.category_id = c.id WHERE LOWER(c.title) LIKE '%webinar%');\""
        ],
        [
          "description" => "Şterg email_stats pentru emailurile de Webinarii",
          "command" => $phpConsolePath."doctrine:query:sql \"DELETE FROM email_stats WHERE email_id IN (SELECT e.id FROM emails e JOIN categories c ON e.category_id = c.id WHERE LOWER(c.title) LIKE '%webinar%');\""
        ],
        [
          "description" => "Actualizează acest utilitar",
          "command" => 'bash '.$dosar_instalare_mautic.'comenzi.sh'
        ]
    ];

      if (isset($commandAleasa)) {
        foreach ($comenzi as $index => $command) {
          if ($command['command'] === $commandAleasa) {
            $descriptionGasita = $command['description'];
            break;
          }
        }
      }

      // Resetați numărul de încercări eșuate
      $_SESSION['incercari_esuate'] = 0;
    } else {
      $parolaCorecta = false;
      // Înregistrați ora încercării de autentificare eșuate și adresa IP a utilizatorului
      $_SESSION['ultima_incercare'] = time();
      $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];

      // Incrementați numărul de încercări eșuate
      if (!isset($_SESSION['incercari_esuate'])) {
        $_SESSION['incercari_esuate'] = 0;
      }
      $_SESSION['incercari_esuate']++;

      // Calculați întârzierea crescatoare
      $intarziere = pow(2, $_SESSION['incercari_esuate']);

      // Verificați dacă a trecut timp de la ultima încercare
      if (isset($_SESSION['ultima_incercare']) && (time() - $_SESSION['ultima_incercare']) < $intarziere) {
        echo "Ați introdus o parolă incorectă. Vă rugăm să așteptați " . $intarziere . " secunde înainte de a încerca din nou.";
      } else {
        echo "Parola incorectă.";
      }
    }
  }
}
?>

<h1>Execută comenzi Mautic</h1>

<form method="post">
  <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
  <label>1. Introdu Parola necesară:<br>
    <input type="password" name="parola" placeholder="parola" value="<?php echo $parola; ?>">
  </label>
  <br><br><br>
<?php if ($parolaCorecta): ?>
  2. Alege o comandă pentru a fi executată:
  <br>
  <?php foreach ($comenzi as $index => $command) : ?>
  <div class="command-container">
    <input type="radio" class="radio-command" id="command<?php echo $index; ?>" name="command" value="<?php echo $index; ?>">
    <label for="command<?php echo $index; ?>" name="description"><?php echo $command['description']; ?></label><br>
    <label for="command<?php echo $index; ?>" name="command"><?php echo $command['command']; ?></label>
  </div>
  <?php endforeach; ?>
  <br><br>
  <label>3. Comanda aleasă: <span id="commanddescription"><?php echo $descriptionGasita; ?></span><br>
    <textarea rows="4" cols="60" name="commandaleasa" placeholder="alege o comandă"><?php echo $commandAleasa; ?></textarea>
  </label><br><br><br>
  <label>4. Durata maximă de execuție: <span id="duratavaloare"><?php echo $durataMaxima; ?></span>s<br>
    10s <input type="range" min="10" max="120" value="<?php echo $durataMaxima; ?>" step="10" name="durataslider"> 120s
  </label><br><br>
<?php endif; ?>
  <input type="submit">
</form>

<?php if ($parolaCorecta): ?>
<hr>

<div id="rezultat">
  <h2>Rezultatul comenzii: <span id="cod-rezultat"><?php echo htmlspecialchars($codRezultat); ?></span></h2>
  <div id="outputdiv">
    <pre id="output"><?php echo htmlspecialchars($output); ?></pre>
  </div>
</div>
<?php endif; ?>

<script>
<?php if ($parolaCorecta): ?>
var slider = document.querySelector('input[name="durataslider"]');
var output = document.getElementById("duratavaloare");
output.innerHTML = slider.value;

slider.oninput = function() {
  output.innerHTML = this.value;
}


// Selectăm toate label-urile cu name="command" din div-urile command-container
var commandLabels = document.querySelectorAll('.command-container label[name="command"]');

// Creăm un array pentru a stoca conținutul text al acestor label-uri
var comenzi = Array.from(commandLabels).map(function(label) {
  return label.textContent;
});

var commandContainers = document.querySelectorAll('.command-container');
commandContainers.forEach(function(container) {
  container.addEventListener('click', function() {
    var radio = container.querySelector('.radio-command');
    var description = container.querySelector('label[name="description"]').textContent;
    var command = container.querySelector('label[name="command"]').textContent;
    
    // Îndepărtează clasa de la toate div-urile
    document.querySelectorAll('.command-container').forEach(function(div) {
      div.classList.remove('div-selectat');
    });
    container.classList.add('div-selectat');
    radio.checked = true;
    var description1 = document.getElementById('commanddescription');
    var command1 = document.querySelector('textarea[name="commandaleasa"]');

    // Verificăm dacă textul din textarea se află în lista de etichete sau dacă este gol
    if (command1.value && !comenzi.includes(command1.value)) {
      // Dacă nu este, afișăm o fereastră de confirmare
      var confirm = window.confirm('Doriți să suprascrieți textul existent?');
      
      // Dacă utilizatorul confirmă, copiem textul etichetei în caseta de text "commandaleasa"
      if (confirm) {
        description1.textContent = description;
        command1.value = command;
      }
    } else {
      // Dacă textul din textarea se află în lista de etichete sau este gol, îl suprascriem
      description1.textContent = description;
      command1.value = command;
    }
  });
});
<?php endif; ?>

window.onload = function() {
  var parola = document.querySelector('input[name="parola"]');
  var submit = document.querySelector('input[type="submit"]');
<?php if ($parolaCorecta): ?>
  var commandContainers = document.querySelectorAll('.command-container');
  var description = document.querySelectorAll('label[name="description"]');
  var command = document.querySelectorAll('label[name="command"]');
  var commandaleasa = document.querySelector('textarea[name="commandaleasa"]');
<?php endif; ?>

  function checkInput() {
    submit.disabled = (parola.value === '' <?php if ($parolaCorecta) echo " || commandaleasa.value === ''"; ?>);
  }

  parola.addEventListener('input', checkInput);
<?php if ($parolaCorecta): ?>
  commandContainers.forEach(function(radio) {
    radio.addEventListener('click', checkInput);
  });
  description.forEach(function(radio) {
    radio.addEventListener('click', checkInput);
  });
  command.forEach(function(radio) {
    radio.addEventListener('click', checkInput);
  });
  commandaleasa.addEventListener('input', checkInput);
<?php endif; ?>

  // Verifică imediat la încărcarea paginii
  checkInput();
};

<?php if ($shouldReload) echo 'location.reload();'; ?>

</script>

</body>
</html>

