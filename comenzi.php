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
// Începeți sesiunea
session_start();
$comenziVersiune = 1.0;
$comenziData = DateTime::createFromFormat('d.m.Y', '31.01.2024');

// Generarea token-ului CSRF (Cross-Site Request Forgery token)
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

require '/var/mautic-crons/mautic.php';

function debugVar($var, $varName){echo $varName . ' = ';var_dump($var);echo ";<br>";}

define('MAUTIC_ROOT_DIR', __DIR__);
$server_name = filter_input(INPUT_SERVER, 'SERVER_NAME');// "test.ionutojica.com"
if (isset($_SERVER['MAUTIC_ROOT'])) {
  // The path to Mautic root.
  // Please note: %kernel.root_dir% = $docroot/app

  // $docroot = filter_input(INPUT_SERVER, 'MAUTIC_ROOT').'/mautic';
  $docroot = filter_input(INPUT_SERVER, 'MAUTIC_ROOT'); // "/var/www/mautic"
} else {
  $docroot = __DIR__; // "/var/www/mautic"
}
$phpConsolePath = 'php '.$docroot.'/bin/console ';

require_once $docroot.'/autoload.php';
require_once $docroot.'/app/AppKernel.php';
require $docroot.'/vendor/autoload.php';

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

use Mautic\CoreBundle\Loader\ParameterLoader;
use Mautic\CoreBundle\Release\ThisRelease;

defined('IN_MAUTIC_CONSOLE') or define('IN_MAUTIC_CONSOLE', 1);

$metadata = ThisRelease::getMetadata();
defined('MAUTIC_VERSION') or define('MAUTIC_VERSION', $metadata->getVersion());

$version = MAUTIC_VERSION;

$request_uri = "//{$server_name}{$_SERVER['REQUEST_URI']}"; // "//test.ionutojica.com/comenzi.php"
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
      $comenzi = json_decode(file_get_contents('comenzi.json'), true);
      
      $element = [
        'description' => file_exists("/var/mautic-crons/DO-NOT-RUN") ? 'Activeaza CronJob-urile' : 'Dezactiveaza CronJob-urile',
        'command' => $dosar_instalare_cron.'schimbaCronJobs.sh'
      ];
      array_unshift($comenzi, $element);
      $durataMaxima = 60;
        
      if (isset($_POST['commandaleasa'])) {

        $commandAleasa = $_POST['commandaleasa'];
        $durataMaxima = (int)$_POST['durataslider']; // este "60"
        set_time_limit($durataMaxima + 20);
        
        $commandGasita = null;
        foreach ($comenzi as $index => $command) {
          if ($command['command'] === $commandAleasa) {
            $descriptionGasita = $command['description'];
            $commandGasita = $command['command'];
            break;
          }
        }
        if ($commandGasita !== null) { $commandAleasa = $commandGasita; }

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
        } else {
          // doar parola a fost introdusa corect, restul formularului nu a fost afisat
          
        }
      }        // Resetați numărul de încercări eșuate
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
<p>Server: <strong style="color:blue"><?php echo $server_name ?></strong> , Versiunea Mautic: <strong style="color:blue"><?php echo $version ?></strong></p>

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
    <textarea rows="4" cols="60" name="commandaleasa" placeholder="alege o comandă"><?php echo $commandGasita; ?></textarea>
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

<p>comenzi.php , Versiunea: <?php echo $comenziVersiune ?> , Data ultimei modificari: <?php echo $comenziData->format('d.m.Y') ?> .</p>
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
    if (parola.value === ''<?php if ($parolaCorecta): ?> || commandaleasa.value === ''<?php endif; ?>) {
      submit.disabled = true;
    } else {
      submit.disabled = false;
    }
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

