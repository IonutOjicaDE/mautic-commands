<?php
session_start();

// Generation of CSRF token (Cross-Site Request Forgery token)
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

require '/var/mautic-crons/mautic.php';

// Check if var $MAUTIC_FOLDER is defined
if (!isset($MAUTIC_FOLDER)) {
  header("HTTP/1.0 404 Not Found");
  echo "Error 404: Utility script not found and not loaded. Please check the location of the script.";
  exit;
}

// If the folder variables are ending with "/", remove it (backward compatibility)
if (substr($MAUTIC_FOLDER, -1) === '/') {
  $MAUTIC_FOLDER = rtrim($MAUTIC_FOLDER, '/');
}
if (substr($CRON_FOLDER, -1) === '/') {
  $CRON_FOLDER = rtrim($CRON_FOLDER, '/');
}
if (substr($ROOT_FILES_FOLDER, -1) === '/') {
  $ROOT_FILES_FOLDER = rtrim($ROOT_FILES_FOLDER, '/');
}

$shouldReload = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $user_token = $_POST['csrf_token'];
  if (empty($user_token) || !hash_equals($_SESSION['csrf_token'], $user_token)) {
    // CSRF Token does not exist or is not the same
    die('CSRF Token is invalid. Reload / Refresh / F5 the page.');
  } else {
    // Process the form
    $password = $_POST['password'];
    
    // Check the password
    if ($password == $MAUTIC_COMMANDS_PASSWORD) {
      $correctPassword = true;
      $maximumDuration = 60;
        
      if (isset($_POST['chosencommand'])) {

        $chosenCommand = $_POST['chosencommand'];
        $maximumDuration = (int)$_POST['durationslider']; // is "60"
        set_time_limit($maximumDuration + 20);

        // Open the process
        $descriptorspec = array(
          0 => array("pipe", "r"),  // stdin
          1 => array("pipe", "w"),  // stdout
          2 => array("pipe", "w")   // stderr
        );
        $process = proc_open($chosenCommand, $descriptorspec, $pipes);

        if (is_resource($process)) {
          // Set the starting time
          $startingTime = microtime(true);

          while (true) {
            // Check if the process is still running
            $status = proc_get_status($process);

            if (!$status['running']) {
              // If the process has finished, close it and exit the loop
              $output = stream_get_contents($pipes[1]);
              fclose($pipes[1]);
              fclose($pipes[2]);
              $exitCode = proc_close($process); // Receive exit code
              break;
            } else if ((microtime(true) - $startingTime) > $maximumDuration) {
              // If the process takes more than maximum duration, close it
              proc_terminate($process);
              $output = "The execution was terminated because the duration exceeded " . $maximumDuration . " seconds.";
              $exitCode = -2; // Set a custom error code for timeout
              break;
            }
            // Wait 0.5s before checking again
            usleep(500000);
          }
        }
        
      }
      $phpConsolePath = "php ${MAUTIC_FOLDER}/bin/console";
      $commands = [
        [
          'description' => file_exists("${CRON_FOLDER}/DO-NOT-RUN") ? 'Enable CronJobs' : 'Disable CronJobs',
          'command' => "bash ${CRON_FOLDER}/switchCronJobs.sh",
          'color' => 'orange orange2'
        ],
        [
          "description" => "List all Mautic Console commands",
          "command" => "${phpConsolePath} list",
          'color' => 'grey grey1'
        ],
        [
          "description" => 'Reset all permissions of the Mautic folders',
          "command" => "bash ${ROOT_FILES_FOLDER}/reset-mautic${MAUTIC_COUNT}-permissions.sh",
          'color' => 'green green1'
        ],
        [
          "description" => "Clear the cache",
          "command" => "${phpConsolePath} cache:clear",
          'color' => 'green green2'
        ],
        [
          "description" => "Create now a backup (of database and Mautic folder)",
          "command" => "bash ${CRON_FOLDER}/cron-backup.sh",
          'color' => 'green green3'
        ],
        [
          "description" => "Update all segments",
          "command" => "${phpConsolePath} mautic:segments:update",
          'color' => 'blue blue1'
        ],
        [
          "description" => "Update all campaigns",
          "command" => "${phpConsolePath} mautic:campaigns:update",
          'color' => 'blue blue2'
        ],
        [
          "description" => "Process all campaigns",
          "command" => "${phpConsolePath} mautic:campaigns:trigger",
          'color' => 'blue blue2'
        ],
        [
          "description" => "Send emails",
          "command" => "${phpConsolePath} mautic:emails:send",
          'color' => 'blue blue3'
        ],
        [
          "description" => "Send newsletters",
          "command" => "${phpConsolePath} mautic:broadcasts:send",
          'color' => 'blue blue3'
        ],
        [
          "description" => "Send SMSes",
          "command" => "${phpConsolePath} mautic:messages:send",
          'color' => 'blue blue3'
        ],
        [
          "description" => "Process scheduled reports",
          "command" => "${phpConsolePath} mautic:reports:scheduler",
          'color' => 'blue blue4'
        ],
        [
          "description" => "Process webhooks",
          "command" => "${phpConsolePath} mautic:webhooks:process",
          'color' => 'blue blue5'
        ],
        [
          "description" => "Update plugins",
          "command" => "${phpConsolePath} mautic:plugins:update",
          'color' => 'green green4'
        ],
        [
          "description" => "Import 600 contacts",
          "command" => "${phpConsolePath} mautic:import --limit=600",
          'color' => 'blue blue5'
        ],
        [
          "description" => "Show the info older than 90 days that can be deleted",
          "command" => "${phpConsolePath} mautic:maintenance:cleanup --no-interaction --days-old=90 --dry-run",
          'color' => 'orange orange2'
        ],
        [
          "description" => "Delete info older than 90 days",
          "command" => "${phpConsolePath} mautic:maintenance:cleanup --no-interaction --days-old=90",
          'color' => 'red red1'
        ],
        [
          "description" => "Deduplicate contacts",
          "command" => "${phpConsolePath} mautic:contacts:deduplicate",
          'color' => 'red red2'
        ],
        [
          "description" => "Delete unused IPs",
          "command" => "${phpConsolePath} mautic:unusedip:delete",
          'color' => 'red red3'
        ],
        [
          "description" => "Update MaxMind database",
          "command" => "${phpConsolePath} mautic:iplookup:download",
          'color' => 'green green5'
        ],
        [
          "description" => "Check migration status",
          "command" => "${phpConsolePath} doctrine:migrations:status",
          'color' => 'orange orange3'
        ],
        [
          "description" => "Validate migration status",
          "command" => "${phpConsolePath} doctrine:schema:validate",
          'color' => 'orange orange4'
        ],
        [
          "description" => "Show SQL commands to update database",
          "command" => "${phpConsolePath} doctrine:schema:update --dump-sql",
          'color' => 'orange orange5'
        ],
        [
          "description" => "Reset stats of emails for Webinar",
          "command" => "${phpConsolePath} doctrine:query:sql \"UPDATE emails SET read_count = 0, sent_count = 0, variant_sent_count = 0, variant_read_count = 0 WHERE id IN (SELECT e.id FROM emails e JOIN categories c ON e.category_id = c.id WHERE LOWER(c.title) LIKE '%webinar%');\"",
          'color' => 'red red4'
        ],
        [
          "description" => "Remove email_stats from emails for Webinar",
          "command" => "${phpConsolePath} doctrine:query:sql \"DELETE FROM email_stats WHERE email_id IN (SELECT e.id FROM emails e JOIN categories c ON e.category_id = c.id WHERE LOWER(c.title) LIKE '%webinar%');\"",
          'color' => 'red red5'
        ],
        [
          "description" => "Optimize now the database (cke visibility and data-empty true)",
          "command" => "bash ${CRON_FOLDER}/cron-database-optimization.sh",
          'color' => 'green green3'
        ],
        [
          "description" => "Update this utility",
          "command" => "bash ${MAUTIC_FOLDER}/commands/commands.sh",
          'color' => 'purple purple1'
        ],
        [
          "description" => "Undo the update of this utility",
          "command" => "bash ${MAUTIC_FOLDER}/commands/commands.sh undo",
          'color' => 'purple purple2'
        ]
      ];

      if (isset($chosenCommand)) {
        foreach ($commands as $index => $command) {
          if ($command['command'] === $chosenCommand) {
            $descriptionFound = $command['description'];
            break;
          }
        }
      }

      // Reset faild tries count
      $_SESSION['failed_auth'] = 0;
    } else {
      $correctPassword = false;
      // Record the time of the faild authentification and the IP
      $_SESSION['last_attempt'] = time();
      $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];

      // Increment the number of failed tries
      if (!isset($_SESSION['failed_auth'])) {
        $_SESSION['failed_auth'] = 0;
      }
      $_SESSION['failed_auth']++;

      // Calculate increasing delay
      $delay = pow(2, $_SESSION['failed_auth']);

      // Check if the necessary delay from the last verification has passed
      if (isset($_SESSION['last_attempt']) && (time() - $_SESSION['last_attempt']) < $delay) {
        echo "You have entered an incorrect password. Please wait " . $delay. " seconds before trying again.";
      } else {
        echo "Incorrect password.";
      }
    }
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Useful commands for Mautic</title>

<style>
  input::placeholder, textarea::placeholder {text-align:center;}
  input[type="password"], textarea {width:80%; padding:10px; border:1px solid gray; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.1);}

  div#outputdiv {width:80%; border:1px solid blue; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.1);}
  pre#output {display:block; margin:10px;}
  #cod-rezultat {font-size:12px; font-weight:bold;}

  input[type="submit"] {background-color:#4CAF50; color:white; padding:14px 20px; margin:8px 0; border:none; cursor:pointer; width:100%; opacity:0.9; border-radius:10px;}
  input[type="submit"]:hover {opacity:1;}
  input[type="submit"]:disabled {background-color:#f0f0f0;cursor:not-allowed;}
  
  .commands-container {display:flex; flex-wrap:wrap; gap:0px;}
  input[type="radio"] {display:none;}
  div.command-container {
    display: flex;
    flex: 0 0 auto;
    margin: 0px;
    padding: 10px;
    cursor: pointer;
    max-width: 230px;
    min-width: 150px;
    box-sizing: content-box;
    white-space: normal; /* permite întreruperea liniei */
    border-radius: 10px;
    border: 5px solid white;
    justify-content: center; /* aliniază conținutul pe orizontală */
    align-items: center; /* aliniază conținutul pe verticală */
    transition: border 1s ease-in, border-radius 1s ease-in, background-color 0.2s ease-out, color 0.2s ease-out ;
  }
  div.command-container:hover { background-color:white; color:black; }
  div.command-container label[name="description"], #commanddescription {font-weight:bold; cursor:pointer;}
  div.command-container label[name="command"] {font-size:0.9em;cursor:pointer; display:none;}
  .div-selectat {color:black !important; background-color:#d0d0d0 !important; border: 5px solid yellow !important;}

  input[type="range"] {appearance:none;-webkit-appearance:none;
    width:80%; max-width:200px; height:15px; border-radius:5px; background:#d3d3d3; outline:none; opacity:0.7;
    -webkit-transition:1.5s; transition:opacity 1.5s;}
  input[type="range"]::-webkit-slider-thumb {-webkit-appearance:none; appearance:none;
    width:25px;height:25px;border-radius:50%; background:#4CAF50; cursor:pointer;}
  input[type="range"]::-moz-range-thumb {width:25px; height:25px; border-radius:50%; background:#4CAF50; cursor:pointer;}

  /* Defining colors for buttons */
  .grey.hover { margin:0px; border: 5px solid #888888; border-radius: 0px; } /* Grey inchis */
  .grey1 { background-color: #555555; color: white; } /* Dark Grey */
  .grey2 { background-color: #888888; color: white; } /* Grey */
  .grey3 { background-color: #e7e7e7; color: black; } /* Light Grey */

  /* Set 1: Shades of blue */
  .blue.hover { margin:0px; border: 5px solid #0000FF; border-radius: 0px;} /* Blue */
  .blue1 { background-color: #00008B; color: white; } /* Dark Blue */
  .blue2 { background-color: #0000FF; color: white; } /* Blue */
  .blue3 { background-color: #008CBA; color: white; } /* Blue pal */
  .blue4 { background-color: #00BFFF; color: black; } /* Light Blue */
  .blue5 { background-color: #00FFFF; color: black; } /* Cyan */

  /* Set 1: Shades of green */
  .green.hover { margin:0px; border: 5px solid #008000; border-radius: 0px;} /* Green */
  .green1 { background-color: #006400; color: white; } /* Dark Green */
  .green2 { background-color: #008000; color: white; } /* Green */
  .green3 { background-color: #04AA6D; color: white; } /* Light Green */
  .green4 { background-color: #20B2AA; color: white; } /* Light Blue-Green */
  .green5 { background-color: #ADFF2F; color: black; } /* Bright Green */
  .green6 { background-color: #98FB98; color: black; } /* Light Mint Green */

  /* Set 1: Shades of red */
  .red.hover { margin:0px; border: 5px solid #f44336; border-radius: 0px;} /* Red */
  .red1 { background-color: #8B0000; color: white; } /* Brown intens */
  .red2 { background-color: #FF0000; color: white; } /* Red intens */
  .red3 { background-color: #f44336; color: white; } /* Red */
  .red4 { background-color: #FFA07A; color: black; } /* Red pal */
  .red5 { background-color: #FF69B4; color: white; } /* Pink intens */
  .red6 { background-color: #FFC0CB; color: black; } /* Pink */

  /* Set 1: Shades of orange */
  .orange.hover { margin:0px; border: 5px solid #FFA500; border-radius: 0px;} /* Orange */
  .orange1 { background-color: #8B4513; color: white; } /* Brown pal */
  .orange2 { background-color: #FF8C00; color: white; } /* Orange intens */
  .orange3 { background-color: #FFA500; color: black; } /* Orange */
  .orange4 { background-color: #FFD700; color: black; } /* Auriu */
  .orange5 { background-color: #FFDAB9; color: black; } /* Orange pal */

  /* Set 1: Shades of purple */
  .purple.hover { margin:0px; border: 5px solid #A020F0; border-radius: 0px;} /* Veronica */
  .purple1 { background-color: #8B008B; color: white; } /* Dark Magenta */
  .purple2 { background-color: #6F3096; color: white; } /* Tacao */
  .purple3 { background-color: #A020F0; color: white; } /* Veronica */
  .purple4 { background-color: #D05FAD; color: white; } /* Hopbush */
  .purple5 { background-color: #E39FF6; color: black; } /* Lavender */

</style>

</head>
<body>



<h1>Execute Mautic commands</h1>

<form method="post">
  <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
  <label>1. Enter the password:<br>
    <input type="password" name="password" placeholder="password" value="<?php echo $password; ?>">
  </label>
  <br><br><br>
<?php if ($correctPassword): ?>
  2. Choose a command to execute:
  <br>
  <div class="commands-container">
    <?php foreach ($commands as $index => $command) : ?>
    <div class="command-container <?php echo htmlspecialchars($command['color']); ?>">
      <input type="radio" class="radio-command" id="command<?php echo $index; ?>" name="command" value="<?php echo $index; ?>">
      <label for="command<?php echo $index; ?>" name="description"><?php echo $command['description']; ?></label><br>
      <label for="command<?php echo $index; ?>" name="command"><?php echo $command['command']; ?></label>
    </div>
    <?php endforeach; ?>
  </div>
  <br><br>
  <label>3. Chosen command: <span id="commanddescription"><?php echo $descriptionFound; ?></span><br>
    <textarea rows="4" cols="60" name="chosencommand" placeholder="alege o comandă"><?php echo $chosenCommand; ?></textarea>
  </label><br><br><br>
  <label>4. Maximum execution duration: <span id="durationvalue"><?php echo $maximumDuration; ?></span>s<br>
    10s <input type="range" min="10" max="120" value="<?php echo $maximumDuration; ?>" step="10" name="durationslider"> 120s
  </label><br><br>
<?php endif; ?>
  <input type="submit">
</form>

<?php if ($correctPassword): ?>
<hr>

<div id="rezultat">
  <h2>Command Output: <span id="cod-rezultat"><?php echo htmlspecialchars($exitCode); ?></span></h2>
  <div id="outputdiv">
    <pre id="output"><?php echo htmlspecialchars($output); ?></pre>
  </div>
</div>
<?php endif; ?>

<script>
<?php if ($correctPassword): ?>
var slider = document.querySelector('input[name="durationslider"]');
var output = document.getElementById("durationvalue");
output.innerHTML = slider.value;

slider.oninput = function() {
  output.innerHTML = this.value;
}


// We select all labels with name="command" from the command-container divs
var commandLabels = document.querySelectorAll('.command-container label[name="command"]');

// We create an array to store the text content of these labels
var commands = Array.from(commandLabels).map(function(label) {
  return label.textContent;
});

var commandContainers = document.querySelectorAll('.command-container');
commandContainers.forEach(function(container) {
  container.addEventListener('click', function() {
    var radio = container.querySelector('.radio-command');
    var description = container.querySelector('label[name="description"]').textContent;
    var command = container.querySelector('label[name="command"]').textContent;
    
    // Removes the class from all divs
    document.querySelectorAll('.command-container').forEach(function(div) {
      div.classList.remove('div-selectat');
    });
    container.classList.add('div-selectat');
    radio.checked = true;
    var description1 = document.getElementById('commanddescription');
    var command1 = document.querySelector('textarea[name="chosencommand"]');

    // We check if the text in the text is in the list of tags or if it is empty
    if (command1.value && !commands.includes(command1.value)) {
      // If it is not, we display a confirmation window
      var confirm = window.confirm('Doriți să suprascrieți textul existent?');
      
      // If the user confirms, we copy the label text into the text box "chosencommand"
      if (confirm) {
        description1.textContent = description;
        command1.value = command;
      }
    } else {
      // If the text in the text is in the tag list or is empty, we overwrite it
      description1.textContent = description;
      command1.value = command;
    }
  });
});
<?php endif; ?>

window.onload = function() {
  var password = document.querySelector('input[name="password"]');
  var submit = document.querySelector('input[type="submit"]');
<?php if ($correctPassword): ?>
  var commandContainers = document.querySelectorAll('.command-container');
  var description = document.querySelectorAll('label[name="description"]');
  var command = document.querySelectorAll('label[name="command"]');
  var chosencommand = document.querySelector('textarea[name="chosencommand"]');
<?php endif; ?>

  function checkInput() {
    submit.disabled = (password.value === '' <?php if ($correctPassword) echo " || chosencommand.value === ''"; ?>);
  }

  password.addEventListener('input', checkInput);
<?php if ($correctPassword): ?>
  commandContainers.forEach(function(radio) {
    radio.addEventListener('click', checkInput);
  });
  description.forEach(function(radio) {
    radio.addEventListener('click', checkInput);
  });
  command.forEach(function(radio) {
    radio.addEventListener('click', checkInput);
  });
  chosencommand.addEventListener('input', checkInput);
<?php endif; ?>

  // Checks immediately on page load
  checkInput();

<?php if ($correctPassword): ?>
  // Select all button sets
  var sets = ['grey', 'blue', 'green', 'red', 'orange', 'purple'];

  sets.forEach(function(set) {
    // Selects all buttons in the current set
    var buttons = document.getElementsByClassName(set);

    // Add 'mouseover' and 'mouseout' events to each button
    for (var i = 0; i < buttons.length; i++) {
      buttons[i].addEventListener('mouseover', function() {
        // Adds the 'hover' class to all buttons in the current set when one is hovering
        for (var j = 0; j < buttons.length; j++) {
          buttons[j].classList.add('hover');
        }
      });

      buttons[i].addEventListener('mouseout', function() {
        // Removes the 'hover' class from all buttons in the current set when the hover ends
        for (var j = 0; j < buttons.length; j++) {
          buttons[j].classList.remove('hover');
        }
      });
    }
  });
<?php endif; ?>
};

<?php if ($shouldReload) echo 'location.reload();'; ?>

</script>

</body>
</html>
