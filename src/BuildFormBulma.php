<?php

declare(strict_types=1);

namespace Src;

use InvalidArgumentException;

class BuildFormBulma
{
    /**
     * This function is used to build a form
     * it takes an array which denotes the type of question
     * When there is a need for new entries, use the newEnt array.
     */
    private array $entKey;

    private string $token;

    private array $entValue;

    private int $entCount;

    /**
     * enter the array to create the form 'name'=> 's' s denotes string, 1 integer, date for date, textera for textera and select is an array ['select' followed by the options]
     * mixed - you can use to generate text, number, select, inputButton
     * textera
     * it also autogenerate the token
     * title of section ( work_information => title).
     */
    public function __construct(public array $question)
    {
        $this->token = urlencode(base64_encode((random_bytes(32))));
        setcookie('XSRF-TOKEN', $this->token, [
            'expires' => time() + 3600,
            'path' => '/',
            'samesite' => 'Lax',
            'secure' => ($_ENV['APP_ENV'] ?? 'production') === 'production',
            'httponly' => false,
        ]);

        $this->entKey = array_keys($this->question);
        $this->entValue = array_values($this->question);
        $this->entCount = count($this->entValue);
        $_SESSION['token'] = $this->token;
    }



    /**
     * Generates an array of days.
     *
     * @return array ['days' => array, 'selected' => int|null]
     * @throws InvalidArgumentException If day range is invalid
     */
    private function createDay(): array
    {
        return range(1, 31);
    }

    /**
     * Generates an array of months.
     *
     * @return array ['months' => array, 'selected' => string|null]
     */
    private function createMonth(): array
    {
        return ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    }

    /**
     * Generates an array of years.
     *
     * @param int $startYear Starting year (default: 1930)
     * @param int|null $endYear Ending year (default: current year)
     * @return array ['years' => array, 'selected' => int|null]
     * @throws InvalidArgumentException If year range is invalid
     */

    private function createYear(int $startYear = 1930, ?int $endYear = null): array
    {
        $endYear = $endYear ?? (int) date('Y');
        if ($startYear > $endYear) {
            throw new InvalidArgumentException('Start year must be less than or equal to end year');
        }
        return range($startYear, $endYear);
    }


    /**
     * important ones are mixed, select-many, setError.
     *
     * example - mixed 'spouse' => ['mixed','label' => ["Spouse's name", "Spouse's mobile", "Spouse's Email"],'attribute' => ['spouseName', 'spouseMobile', 'spouseEmail'],'placeholder' => ['Toyin', '23480364168089', "toyin@gmail.com"], 'inputType' => ['text', 'text', 'email'],'icon' => ['<i class="fas fa-user"></i>','<i class="fas fa-user"></i>','<i class="fas fa-envelope-square"></i>']],
     *
     *
     * example select-many  'married_gender' => ['select-many','label' => ['Marital status', 'gender']'attribute' => ['maritalStatus', 'gender'],'options' => [['select', 'Yes', 'No'],['select', 'Male', 'Female']],'icon' => ['<i class="far fa-kiss-wink-heart"></i>','<i class="fas fa-user-friends"></i>',]],
     *
     *
     * example showError  nameKey => showError - the namekey should be the id of the div or form that will release the error. See Login or Register.js for a clear example
     *
     * example button_captcha  'submit'=> ['button_captcha', 'js'=> 'loginSubmission', 'key'=>getenv('RECAPTCHA_KEY')],
     */
    public function genForm(): void
    {


        for ($i = 0; $i < $this->entCount; ++$i) {
            $value = isset($_POST['submit']) ? $_POST[$this->entKey[$i]] : '';

            $var = strtoupper(preg_replace('/[^0-9A-Za-z@.]/', ' ', $this->entKey[$i]));
            $nameKey = $this->entKey[$i];
            $value ??= '';
            $multiple = ''; // multiple for file input
            $fileName = "";

            if ($this->entValue[$i] === 'text') {
                echo <<<HTML
                    <div class="field">
                        <label class="label" for="$nameKey"><b>$var</b></label>
                        <div class="control">
                            <input type="text" autocomplete="new-$nameKey" class="input" placeholder="PLEASE ENTER YOUR $var" name="$nameKey" value="$value"  id="{$nameKey}" required>
                            <p class="help" id="{$nameKey}_help"></p>
                            <p class="help" id="{$nameKey}_error"></p>
                        </div>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i][0] === 'text-icon') {
                $fontAwesome = $this->entValue[$i][1];
                echo <<<HTML
                    <div class="field">
                        <label class="label" for="$nameKey"><b>$var</b></label>
                        <div class="control has-icons-left has-icons-right">
                            <input type="text" autocomplete="new-$nameKey" class="input" placeholder="$var" required name="$nameKey" value="$value">
                            <span class="icon is-small is-left">
                                $fontAwesome
                            </span>
                            <span class="icon is-small is-right">
                                <i class="fas fa-check fa-xs"></i>
                            </span>
                            <p class="help" id="{$nameKey}_help"></p>
                            <p class="help" id="{$nameKey}_error"></p>
                        </div>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i] === 'integer') {
                echo <<<HTML
                    <div class="field">
                        <label class="label" for="$nameKey"><b>$var</b></label>
                        <div class="control">
                            <input type="number" autocomplete="new-$nameKey" class="input" placeholder="$var" required name="$nameKey" value="$value">
                            <p class="help" id="{$nameKey}_help"></p>
                            <p class="help" id="{$nameKey}_error"></p>
                        </div>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i] === 'date') {
                echo <<<HTML
                    <div class="field">
                        <label class="label" for="$nameKey" ><b>$var</b></label>
                        <div class="control">
                            <input type="date" autocomplete="new-$nameKey" class="input" placeholder="$var" required name="$nameKey" value="$value">
                            <p class="help" id="{$nameKey}_help"></p>
                            <p class="help" id="{$nameKey}_error"></p>
                        </div>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i][0] === 'select') {
                $options = $this->entValue[$i];
                echo <<<HTML
                    <div class="field">
                        <label class="label" for="$nameKey"><b>$var</b></label>
                        <div class="control">
                            <div class="select">
                                <select name="$nameKey">
                    HTML;
                foreach ($options as $option) {
                    echo "<option value=\"$option\">$option</option>";
                }
                echo <<<HTML
                                </select>
                            </div>
                            <p class="help" id="{$nameKey}_help"></p>
                            <p class="help" id="{$nameKey}_error"></p>
                        </div>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i][0] === 'select-icon') {
                $fontAwesome = $this->entValue[$i][1];
                echo <<<HTML
                    <div class="field">
                        <label class="label" for="$nameKey"><b>$var</b></label>
                        <div class="control has-icons-left">
                            <div class="select">
                                <select name="$nameKey">
                    HTML;
                for ($y = 1; $y < count($this->entValue[$i]); ++$y) {
                    echo '<option>' . $this->entValue[$i][$y] . '</option>';
                }
                echo <<<HTML
                                </select>
                            </div>
                            <span class="icon is-small is-left">
                                $fontAwesome
                            </span>
                            <p class="help" id="{$nameKey}_help"></p>
                            <p class="help" id="{$nameKey}_error"></p>
                        </div>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i][0] === 'textarea') {
                echo <<<HTML
                    <div class="field">
                        <label for="$nameKey" class="label" ><b>{$this->entValue[$i][1]}</b></label>
                        <div class="control">
                            <textarea class="textarea is-link" autocomplete="new-$nameKey"  id="{$nameKey}" required name="$nameKey" row="10">$value</textarea>
                            <p class="help" id="{$nameKey}_help"></p>
                            <p class="help" id="{$nameKey}_error"></p>
                        </div>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i] === 'email') {
                echo <<<HTML
                    <div class="field">
                        <label for="email" class="label" ><b>$var</b></label>
                        <div class="control has-icons-left has-icons-right">
                            <input type="email" id="{$nameKey}" placeholder="alex@gmail.com" class="input $nameKey is-medium" autocomplete="username" name="$nameKey" value="$value">
                            <span class="icon is-small is-left">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <span class="icon is-small is-right">
                                <i class="fas fa-check"></i>
                            </span>
                            <p class="help" id="{$nameKey}_help"></p>
                            <p class="help error" id="{$nameKey}_error"></p>
                        </div>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i] === 'password') {
                echo <<<HTML
                    <div class="field">
                        <label for class="label"><b>$var</b></label>
                        <div class="control has-icons-left has-icons-right">
                            <input type="password" id="{$nameKey}" placeholder="password" autocomplete="new-password" class="input $nameKey is-medium" name="$nameKey">
                            <span class="icon is-small is-left">
                                <i class="fas fa-lock"></i>
                            </span>
                            <span class="icon is-small is-right">
                                <i class="fas fa-check"></i>
                            </span>
                            <p class="help" id="{$nameKey}_help"></p>
                            <p class="help" id="{$nameKey}_error"></p>
                        </div>
                    </div>
                    HTML;
            } elseif ($this->entKey[$i] === 'checkbox') {
                echo <<<HTML
                    <div class="field">
                        <div class="control">
                            <label class="checkbox">
                                <input type="checkbox" id="checkbox" name="$nameKey">
                                {$this->entValue[$i]}
                            </label>
                            <p class="help" id="{$nameKey}_error"></p>
                        </div>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i] === 'button') {
                echo <<<HTML
                    <div class="field">
                        <p class="control">
                            <button name="button" id="button" type="button" class="button is-success button is-large is-fullwidth">
                                {$nameKey}
                            </button>
                        </p>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i][0] === 'button_captcha') {
                $js = $this->entValue[$i]['js'];
                $siteKey = $this->entValue[$i]['key'];
                $action = $this->entValue[$i]['action'];
                echo <<<HTML
                    <div class="field">
                        <p class="control">
                            <button data-sitekey=$siteKey data-callback=$js id="button" data-action=$action class="button is-success button is-large is-fullwidth g-recaptcha">
                                {$nameKey}
                            </button>
                        </p>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i] === 'submit') {
                echo <<<HTML
                    <div class="field">
                        <p class="control">
                            <button name="submit" id="submit" type="submit" class="button is-success button is-large is-fullwidth submit">
                                Submit
                            </button>
                        </p>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i] === 'token') {
                echo <<<HTML
                    <div class="field">
                        <div class="control">
                            <input type="hidden" class="input" id="token" name="token" value="{$this->token}">
                        </div>
                    </div>
                    HTML;
            } elseif ($this->entKey[$i] === 'blank') {
                echo <<<HTML
                       <div class="field"></div>
                    HTML;
                for ($y = 1; $y < count($this->entKey[$i]); ++$y) {
                    $name = $this->entValue['type'][$y];
                    $label = $this->entValue['label'][$y];
                    $namePlaceholder = strtoupper(preg_replace('/[^0-9A-Za-z@.]/', ' ', $name));
                    echo <<<HTML
                        <div class="field">
                            <label class="label"><b>$label</b></label>
                            <div class="field-body">
                                <p class="control is-expanded has-icons-left">
                                    <input class="input" type="text" name="$name" placeholder="$namePlaceholder">
                                    <span class="icon is-small is-left">
                                        <i class="fas fa-user"></i>
                                    </span>
                                </p>
                            </div>
                        </div>
                        HTML;
                }
            } elseif ($this->entValue[$i] === 'birthday') {
                $divID = $this->entValue[$i];
                echo <<<HTML
                            <div class="field" id="$divID">
                                <label for="{$nameKey}" class="label is-medium"><b>$var</b></label>
                                <p class="help error" id="{$nameKey}_error"></p>
                                <div class="field-body">
                                    <div class="field">
                                        <div class="control">
                                            <div class="select is-fullwidth is-medium">
                                                <select name="day" id="day">
                                                    <option selected value="select">Day</option>
                    HTML;
                foreach ($this->createDay() as $day) {
                    echo "<option value=\"$day\">$day</option>";
                }
                echo <<<HTML
                                        </select>
                                    </div>
                                </div>
                                <p class="help error" id="day_error"></p>
                            </div>
                            <div class="field">
                                <div class="control">
                                    <div class="select is-fullwidth is-medium">
                                        <select name="month" id="month">
                                            <option selected value="select">Month</option>
                    HTML;
                foreach ($this->createMonth() as $month) {
                    echo "<option value=\"$month\">$month</option>";
                }
                echo <<<HTML
                                        </select>
                                    </div>
                                </div>
                                <p class="help error" id="month_error"></p>
                            </div>
                            <div class="field">
                                <div class="control">
                                    <div class="select is-fullwidth is-medium">
                                        <select name="year" id="year">
                                            <option selected value="select">Year</option>
                    HTML;
                foreach ($this->createYear() as $year) {
                    echo "<option value=\"$year\">$year</option>";
                }
                echo <<<HTML
                                        </select>
                                    </div>
                                </div>
                                <p class="help" error id="year_error"></p>
                            </div>
                        </div>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i][0] === 'slider') {
                $fAwesome = $this->entValue[$i][1];
                $sliderId = $this->entValue[$i][2];
                $inputId = $this->entValue[$i][3];
                echo <<<HTML
                    <div class="field">
                        <label for="{$inputId}" class="label" ><b>$var</b></label>
                        <div class="field-body">
                            <div class="field">
                                <div id="$sliderId"></div>
                            </div>
                            <div class="field">
                                <p class="control is-expanded has-icons-left">
                                    <input class="input is-success" type="number" name="$inputId" id="$inputId" value="$value" readonly>
                                    <span class="icon is-small is-left">$fAwesome</span>
                                    <span class="icon is-small is-right">
                                        <i class="fas fa-check"></i>
                                    </span>
                                </p>
                                <p class="help" id="{$inputId}_help"></p>
                                <p class="help error" id="{$inputId}_error"></p>
                            </div>
                        </div>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i][0] === 'mixed') {
                $divID = $this->entKey[$i];
                echo <<<HTML
                    <div class="field" id="$divID">
                        <div class="field-body">
                    HTML;
                for ($y = 0; $y < count($this->entValue[$i]['label']); ++$y) {
                    $label = empty($this->entValue[$i]['label'][$y]) ? '' : $this->entValue[$i]['label'][$y];
                    $name = empty($this->entValue[$i]['attribute'][$y]) ? '' : $this->entValue[$i]['attribute'][$y];
                    $placeholder = empty($this->entValue[$i]['placeholder'][$y]) ? '' : $this->entValue[$i]['placeholder'][$y];
                    $id = $name;
                    $error = $name . '_error';
                    $help = $name . '_help';
                    $cleanLabel = strtoupper($label);
                    $value = empty($this->entValue[$i]['value'][$y]) ? '' : $this->entValue[$i]['value'][$y];

                    $labelType = $this->entValue[$i]['inputType'][$y] ? $this->entValue[$i]['inputType'][$y] : '';
                    $icon = $this->entValue[$i]['icon'][$y] ?? '';

                    $hasIconLeft = (isset($this->entValue[$i]['icon'][$y]) ? 'has-icons-left' : '');
                    $hasImg = ($this->entValue[$i]['img'][$y] ?? '');
                    $multiple = ''; // multiple for file input

                    if ($labelType === 'select') {
                        echo <<<HTML
                            <div class="field $name" id="{$name}_div">
                                <label class="label is-medium"><b>$cleanLabel</b></label>
                                <div class="control has-icons-left has-icons-right">
                                    <div class="select is-fullwidth is-medium">
                                        <select class="input is-medium" id="$id" name="$name">
                                        
                            HTML;

                        if ($this->entValue[$i]['options'][$y]) {
                            $decide = $this->entValue[$i]['options'][$y];

                            foreach ($decide as $value) {
                                echo "<option> $value </option>";
                            }
                        }
                        echo <<<HTML
                                        </select>
                                        <span class="icon is-small is-left">$icon</span>
                                        <!-- <span class="icon is-small is-right">
                                            <i class="fas fa-angle-down fasCol"></i>
                                        </span> -->
                                    </div>
                                    <p class="help" id="$help"></p>
                                    <p class="help error" id="$error"></p>
                                </div>
                            </div>
                            HTML;
                    } elseif ($labelType === 'inputButton') {
                        echo <<<HTML
                                                    
                            <div class="field $name has-addons has-addons-left" id="{$name}_div">
                                

                                <div class="control is-expanded $hasIconLeft">
                                    <input for="{$name}" class="input $name input is-medium" id="{$name}" type="text" placeholder="$cleanLabel" name="$name">
                                    <span class="icon is-small is-left">$icon</span>
                                    <p class="help" id="{$name}_help"></p>
                                </div>
                                <div class="control">
                                    <button class="button is-success is-medium" id="{$name}_button">Search</button>
                                </div>

                            </div>
                            HTML;
                    } elseif ($labelType === 'cardSelect') {
                        echo <<<HTML
                                    <div class="$name column" id="{$name}_div">
                                        <div class="card h-100 hidden">
                                            <div class="card-image">
                                                <figure class="image is-4by3">
                                                <img src="$hasImg"
                                                    alt="Placeholder image"
                                                />
                                                </figure>
                                            </div>

                                             <header class="card-header">
                                                <p class="card-header-title">>$cleanLabel</p>
                                             
                                            </header>
                                   
                                        <div class="card-content">

                                            <div class="content">

                                        
                                    
                                        
                            HTML;
                        if ($this->entValue[$i]['options'][$y]) {
                            echo <<<HTML
                                                    <select class="select is-primary" arial-label='Default' id="$id" name="$name">
                                                        
                                                        <option value='$value'> <span style="font-size: 20px;">Choose </span></option>
                                HTML;
                            $decide = $this->entValue[$i]['options'][$y];

                            foreach ($decide as $value => $option) {
                                echo "<option value='$value'>
                                            <span style='font-size: 20px;'> $option 
                                            </span> </option>";
                            }
                            echo <<<HTML
                                                    </select>
                                HTML;
                        } else {
                            echo <<<HTML
                                                    <input type="text" class="input is-primary" maxlength="30" minlength="1" name="$name" id="$id" placeholder="$placeholder" autocomplete="$name">
                                HTML;
                        }
                        echo <<<HTML
                                                   
                                                 
                                               
                                                <!-- <small id="$help" class="form-text text-muted"></small>
                                                <small id="$error" class="form-text text-danger"></small> -->
                                            </div>
                                            </div>
                                            </div>
                                             </div>
                                            </div>
                                            </div>
                            HTML;
                    } elseif ($labelType === 'file') {

                        if (strpos($name, '[]') !== false) {
                            $name = str_replace(['[', ']'], '', $name);
                            $multiple = "multiple";
                        }
                        echo <<<HTML
                            <div class="field $name" id="{$name}_div">
                                <label class="label is-medium"><b>$cleanLabel</b></label>
                                 <div class="control">
                                <div class="file has-name {$name}_div">
                                    <label class="file-label">
                                    <input class="file-input $name is-medium" type="file" name="$name" id="{$name}" placeholder="$placeholder" autocomplete="$name" $multiple>
                                    <span class="icon is-small is-left">$icon</span>
                                     <span class="file-cta">
                                        <span class="file-icon">
                                            <i class="fas fa-upload"></i>
                                        </span>
                                        <span class="file-label"> Choose a file… </span>
                                        </span>
                                        <span class="file-name">No file uploaded </span>
                                    </label>
                                    </div>
                                    <p class="help" id="{$name}_help"></p>
                                    <p class="help error" id="{$name}_error"></p>
                                </div>
                                </div>
                            </div>
                            HTML;
                    } elseif ($labelType === 'textarea') {
                        echo <<<HTML
                    <div class="field" id="{$nameKey}_div">
                        <label for="$nameKey" class="label is-medium" ><b>$cleanLabel</b></label>
                        <div class="control is-expanded">
                            <textarea class="textarea is-link" autocomplete="new-$nameKey"  id="{$nameKey}" required name="$nameKey" row="10">$value</textarea>
                            <p class="help" id="{$nameKey}_help"></p>
                            <p class="help" id="{$nameKey}_error"></p>
                        </div>
                    </div>
                    HTML;
                    } else {
                        echo <<<HTML
                            <div class="field $name" id="{$name}_div">
                                <label for="{$name}" class="label is-medium"><b>$cleanLabel</b></label>
                                <div class="control is-expanded $hasIconLeft">
                                    <input class="input $name input is-medium" type="$labelType" value="$value" maxlength="30" minlength="1" name="$name" id="$id" placeholder="$placeholder" autocomplete="$name">
                                    <span class="icon is-small is-left">$icon</span>
                                    <p class="help" id="{$name}_help"></p>
                                    <p class="help error" id="{$name}_error"></p>
                                </div>
                            </div>
                            HTML;
                    }
                }
                echo <<<HTML
                    </div>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i][0] === 'mixed_nested') {
                $divID = $this->entKey[$i];
                echo <<<HTML
                    <div class="field" id="$divID">
                        <div class="columns">
                    HTML;
                for ($y = 0; $y < count($this->entValue[$i]['label']); ++$y) {
                    $label = empty($this->entValue[$i]['label'][$y]) ? '' : $this->entValue[$i]['label'][$y];
                    $name = empty($this->entValue[$i]['attribute'][$y]) ? '' : $this->entValue[$i]['attribute'][$y];
                    $nestedName = $divID . "['" . $name . "']";
                    $placeholder = empty($this->entValue[$i]['placeholder'][$y]) ? '' : $this->entValue[$i]['placeholder'][$y];
                    $id = $name;
                    $error = $name . '_error';
                    $help = $name . '_help';
                    $cleanLabel = strtoupper($label);
                    $value = empty($this->entValue[$i]['value'][$y]) ? '' : $this->entValue[$i]['value'][$y];

                    $labelType = $this->entValue[$i]['inputType'][$y] ? $this->entValue[$i]['inputType'][$y] : '';
                    $icon = $this->entValue[$i]['icon'][$y] ?? '';

                    $hasIconLeft = (isset($this->entValue[$i]['icon'][$y]) ? 'has-icons-left' : '');
                    $hasImg = ($this->entValue[$i]['img'][$y] ?? '');
                    $multiple = ''; // multiple for file input

                    if ($labelType === 'select') {
                        echo <<<HTML
                            <div class="field $name" id="{$name}_div">
                                <label for="{$name}" class="label is-medium" ><b>$cleanLabel</b></label>
                                <div class="control has-icons-left has-icons-right">
                                    <div class="select is-fullwidth is-medium">
                                        <select class="input is-medium" id="$id" name="$name">
                                        
                            HTML;

                        if ($this->entValue[$i]['options'][$y]) {
                            $decide = $this->entValue[$i]['options'][$y];

                            foreach ($decide as $value) {
                                echo "<option> $value </option>";
                            }
                        }
                        echo <<<HTML
                                        </select>
                                        <span class="icon is-small is-left">$icon</span>
                                        <!-- <span class="icon is-small is-right">
                                            <i class="fas fa-angle-down fasCol"></i>
                                        </span> -->
                                    </div>
                                    <p class="help" id="$help"></p>
                                    <p class="help error" id="$error"></p>
                                </div>
                            </div>
                            HTML;
                    } elseif ($labelType === 'inputButton') {
                        echo <<<HTML
                                                    
                            <div class="field $name has-addons has-addons-left" id="{$name}_div">
                                

                                <div class="control is-expanded $hasIconLeft">
                                    <input for="{$name}" class="input $name input is-medium" id="{$name}" type="text" placeholder="$cleanLabel" name="$name">
                                    <span class="icon is-small is-left">$icon</span>
                                    <p class="help" id="{$name}_help"></p>
                                </div>
                                <div class="control">
                                    <button class="button is-success is-medium" id="{$name}_button">Search</button>
                                </div>

                            </div>
                            HTML;
                    } elseif ($labelType === 'cardSelect') {
                        echo <<<HTML
                                    <div class="$name column" id="{$name}_div">
                                        <div class="card h-100 hidden">
                                            <div class="card-image">
                                                <figure class="image is-4by3">
                                                <img src="$hasImg"
                                                    alt="Placeholder image"
                                                />
                                                </figure>
                                            </div>

                                             <header class="card-header">
                                                <p class="card-header-title">>$cleanLabel</p>
                                             
                                            </header>
                                   
                                        <div class="card-content">

                                            <div class="content">

                                        
                                    
                                        
                            HTML;
                        if ($this->entValue[$i]['options'][$y]) {
                            echo <<<HTML
                                                    <select class="select is-primary" arial-label='Default' id="$id" name="$name">
                                                        
                                                        <option value='$value'> <span style="font-size: 20px;">Choose </span></option>
                                HTML;
                            $decide = $this->entValue[$i]['options'][$y];

                            foreach ($decide as $value => $option) {
                                echo "<option value='$value'>
                                            <span style='font-size: 20px;'> $option 
                                            </span> </option>";
                            }
                            echo <<<HTML
                                                    </select>
                                HTML;
                        } else {
                            echo <<<HTML
                                                    <input type="text" class="input is-primary" maxlength="30" minlength="1" name="$name" id="$id" placeholder="$placeholder" autocomplete="$name">
                                HTML;
                        }
                        echo <<<HTML
                                                   
                                                 
                                               
                                                <!-- <small id="$help" class="form-text text-muted"></small>
                                                <small id="$error" class="form-text text-danger"></small> -->
                                            </div>
                                            </div>
                                            </div>
                                             </div>
                                            </div>
                                            </div>
                            HTML;
                    } elseif ($labelType === 'file') {

                        if (strpos($name, '[]') !== false) {
                            $name = str_replace(['[', ']'], '', $name);
                            $multiple = "multiple";
                        }
                        echo <<<HTML
                            <div class="field $name" id="{$name}_div">
                                <label class="label is-medium"><b>$cleanLabel</b></label>
                                 <div class="control">
                                <div class="file has-name {$name}_div">
                                    <label class="file-label">
                                    <input class="file-input $name is-medium" type="file" name="$name" id="{$name}" placeholder="$placeholder" autocomplete="$name" $multiple>
                                    <span class="icon is-small is-left">$icon</span>
                                     <span class="file-cta">
                                        <span class="file-icon">
                                            <i class="fas fa-upload"></i>
                                        </span>
                                        <span class="file-label"> Choose a file… </span>
                                        </span>
                                        <span class="file-name">No file uploaded </span>
                                    </label>
                                    </div>
                                    <p class="help" id="{$name}_help"></p>
                                    <p class="help error" id="{$name}_error"></p>
                                </div>
                                </div>
                            </div>
                            HTML;
                    } elseif ($labelType === 'textarea') {
                        echo <<<HTML
                    <div class="field" id="{$nameKey}_div">
                        <label for="$nameKey" class="label is-medium" ><b>$cleanLabel</b></label>
                        <div class="control is-expanded">
                            <textarea class="textarea is-link" autocomplete="new-$nameKey"  id="{$nameKey}" required name="$nameKey" row="10">$placeholder</textarea>
                            <p class="help" id="{$nameKey}_help"></p>
                            <p class="help" id="{$nameKey}_error"></p>
                        </div>
                    </div>
                    HTML;
                    } else {
                        echo <<<HTML
                            <div class="field $name" id="{$name}_div">
                                <label class="label is-medium"><b>$cleanLabel</b></label>
                                <div class="control is-expanded $hasIconLeft">
                                    <input for="{$name}" class="input $name input is-medium" type="$labelType" value="$value" maxlength="30" minlength="1" name="$nestedName" id="$id" placeholder="$placeholder" autocomplete="$name">
                                    <span class="icon is-small is-left">$icon</span>
                                    <p class="help" id="{$name}_help"></p>
                                    <p class="help error" id="{$name}_error"></p>
                                </div>
                            </div>
                            HTML;
                    }
                }
                echo <<<HTML
                    </div>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i][0] === 'select-many') {
                $divID = $this->entKey[$i];
                echo <<<HTML
                    <div class="field" id="$divID">
                        <div class="field-body">
                    HTML;
                for ($y = 0; $y < count($this->entValue[$i]['label']); ++$y) {
                    $options = $this->entValue[$i]['options'][$y];
                    $label = $this->entValue[$i]['label'][$y];
                    $name = $this->entValue[$i]['attribute'][$y];
                    $id = $name;
                    $error = $name . '_error';
                    $help = $name . '_help';
                    $cleanLabel = strtoupper($label);
                    $icon = $this->entValue[$i]['icon'][$y];
                    echo <<<HTML
                        <div class="field" id="{$name}_div">
                            <label for="{$name}" class="label is-medium"><b>$cleanLabel</b></label>
                            <div class="control has-icons-left has-icons-right">
                                <select class="input is-medium" id="$id" name="$name">
                        HTML;
                    foreach ($options as $option) {
                        echo "<option value=\"$option\">$option</option>";
                    }
                    echo <<<HTML
                                </select>
                                <span class="icon is-small is-left">$icon</span>
                                <span class="icon is-small is-right">
                                    <i class="fas fa-angle-down fasCol"></i>
                                </span>
                            </div>
                            <p class="help" id="$help"></p>
                            <p class="help error" id="$error"></p>
                        </div>
                        HTML;
                }
                echo <<<HTML
                    </div>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i] === 'title') {
                echo <<<HTML
                    <hr><br>
                    <p id="{$nameKey}1" class="title is-3 is-spaced has-text-centered has-text-link is-primary the-title">$var</p><br>
                    <p class="subtitle is-6 has-text-centered" id="{$nameKey}_help"></p>
                    HTML;
            } elseif ($this->entValue[$i] === 'subtitle') {
                echo <<<HTML
                    <h2 class="subtitle has-text-centered is-primary">$var</h2>
                    HTML;
            } elseif ($this->entValue[$i][0] === 'radio') {
                echo <<<HTML
                    <hr><br>
                    <div class="control">
                        <label class="radio">
                            {$this->entValue[$i][1]}
                            <input type="radio" name="{$this->entKey[$i]}" id="{$this->entKey[$i]}_yes" class="{$this->entKey[$i]}">
                            {$this->entValue[$i][2]}
                        </label>
                        <label class="radio">
                            <input type="radio" name="{$this->entKey[$i]}" id="{$this->entKey[$i]}_no" class="{$this->entKey[$i]}">
                            {$this->entValue[$i][3]}
                        </label>
                        <p class="help" id="{$this->entKey[$i]}_error"></p>
                    </div>
                    <br>
                    HTML;
            } elseif ($this->entValue[$i] === 'empty') {
                echo '';
            } elseif ($this->entValue[$i] === 'showError') {
                echo <<<HTML
                    <div id="setLoader" class="loader" tabindex="-1" style="display: none;"></div>
                        <div class="notification" id="$nameKey" style="display: none;">
                            <p id="error"></p>
                        </div>
                    HTML;
            } elseif ($this->entValue[$i] === 'recaptcha') {
                $recaptcha = $_ENV['RECAPTCHA_KEY'];

                echo <<<HTML
                    <div class="field">
                        <div class="g-recaptcha" data-sitekey="$recaptcha" data-theme="dark"></div>
                        </div>

                HTML;

            } elseif ($this->entValue[$i] == 'showPassword') {
                echo <<<HTML
                       <label class="checkbox">
                        <input type="checkbox" id="showPassword">
                            Show Password
                        </label><br>
                    HTML;
            } elseif ($this->entValue[$i] === 'file') {

                if (strpos($nameKey, '[]') !== false) {
                    $fileName = str_replace(['[', ']'], '', $nameKey);
                    $multiple = "multiple";
                } else {
                    $multiple = '';
                    $fileName = $nameKey;
                }
                echo <<<HTML
                    <div class="field">
                        <label for="$fileName" class="label" ><b>$var</b></label>
                        <!-- <div class="control"> -->
                            <div class="file has-name is-boxed">
                                <label class="file-label">
                                    <input class="file-input" type="file" name="$nameKey" id="$fileName" $multiple />
                                    <span class="file-cta">
                                    <span class="file-icon">
                                        <i class="fas fa-upload"></i>
                                    </span>
                                    <span class="file-label"> Choose a file… </span>
                                    </span>
                                    <span class="file-name"> No file chosen </span>
                                </label>
                                </div>
                            <p class="help" id="{$fileName}_help"></p>
                            <p class="help error" id="{$fileName}_error"></p>
                        <!-- </div> -->
                    </div>
                    HTML;
            } else {
                echo "Invalid form element type: {$this->entValue[$i]}";
            }
        }
    }
}
