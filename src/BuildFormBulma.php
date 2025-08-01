<?php

declare(strict_types=1);

namespace Src;

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

    public ?string $ref = null;

    public ?string $year = null;

    public ?string $month = null;

    private array $setYear = [];

    private array $setDay = [];

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
            'httponly' => false, // Allow JavaScript to read it for Axios
        ]);
    }

    /**
     * it extracts out the values of the form. this is what we use to decide the type of question.
     *
     * @return array
     */
    public function setEntValue(): array
    {
        $this->entValue = array_values($this->question);
        $this->entCount = count($this->entValue);

        return $this->entValue;
    }

    /**
     * to create the year of birth
     * set the years and create an array.
     */
    private function createYear(int $startVar, int $dayOrYear): array
    {
        $setYear = [];
        for ($i = $startVar; $i <= $dayOrYear; ++$i) {
            $setYear[] = $i;
        }

        return $setYear;
    }

    private function createDay(int $startVar, int $dayOrYear): array
    {
        $setDay = [];
        for ($i = $startVar; $i < $dayOrYear; ++$i) {
            $setDay[] = $i;
        }

        return $setDay;
    }

    public function getYear(): void
    {
        $yearLimit = (int) date('Y');
        $this->setYear = $this->createYear(1930, $yearLimit);

        foreach ($this->setYear as $no) {
            echo "<option value=\"$no\">$no</option>";
        }
    }

    private function getDay()
    {
        $this->setDay = $this->createDay(0o1, 32);
        foreach ($this->setDay as $no) {
            echo "<option value=\"$no\">$no</option>";
        }
    }

    /**
     * function to set the key of the form. Keys are the names of questions and the names of the database.
     */
    public function setEntKey(): array
    {
        $this->entKey = array_keys($this->question);

        return $this->entKey;
    }

    public function setSessionToken(): string
    {
        $_SESSION['token'] = $this->token;

        return $_SESSION['token'];
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
        $this->setEntValue();
        $this->setEntKey();
        $this->setSessionToken();

        for ($i = 0; $i < $this->entCount; ++$i) {
            $value = isset($_POST['submit']) ? $_POST[$this->entKey[$i]] : '';

            $var = strtoupper(preg_replace('/[^0-9A-Za-z@.]/', ' ', $this->entKey[$i]));
            $nameKey = $this->entKey[$i];
            $value ??= '';

            if ($this->entValue[$i] === 'text') {
                echo <<<HTML
                    <div class="field">
                        <label class="label" id="$nameKey"><b>$var</b></label>
                        <div class="control">
                            <input type="text" autocomplete="new-$nameKey" class="input" placeholder="PLEASE ENTER YOUR $var" name="$nameKey" value="$value"  id="{$nameKey}_id" required>
                            <p class="help" id="{$nameKey}_help"></p>
                            <p class="help" id="{$nameKey}_error"></p>
                        </div>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i][0] === 'text-icon') {
                $fontAwesome = $this->entValue[$i][1];
                echo <<<HTML
                    <div class="field">
                        <label class="label" id="$nameKey"><b>$var</b></label>
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
                        <label class="label" id="$nameKey"><b>$var</b></label>
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
                        <label class="label" id="$nameKey"><b>$var</b></label>
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
                        <label class="label" id="$nameKey"><b>$var</b></label>
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
                        <label class="label" id="$nameKey"><b>$var</b></label>
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
            } elseif ($this->entValue[$i] === 'textarea') {
                echo <<<HTML
                    <div class="field">
                        <label class="label" id="$nameKey"><b>$var</b></label>
                        <div class="control">
                            <textarea class="textarea" autocomplete="new-$nameKey" placeholder="$var" required name="$nameKey">$value</textarea>
                            <p class="help" id="{$nameKey}_help"></p>
                            <p class="help" id="{$nameKey}_error"></p>
                        </div>
                    </div>
                    HTML;
            } elseif ($this->entValue[$i] === 'email') {
                echo <<<HTML
                    <div class="field">
                        <label class="label" id="$nameKey"><b>$var</b></label>
                        <div class="control has-icons-left has-icons-right">
                            <input type="email" id="{$nameKey}_id" placeholder="alex@gmail.com" class="input $nameKey is-medium" autocomplete="username" name="$nameKey" value="$value">
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
                        <label class="label" id="$nameKey"><b>$var</b></label>
                        <div class="control has-icons-left has-icons-right">
                            <input type="password" id="{$nameKey}_id" placeholder="password" autocomplete="new-password" class="input $nameKey is-medium" name="$nameKey">
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
                            <label class="label" id="$label"><b>$label</b></label>
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
                                <label class="label is-medium" id="$nameKey"><b>$var</b></label>
                                <p class="help error" id="{$nameKey}_error"></p>
                                <div class="field-body">
                                    <div class="field">
                                        <div class="control">
                                            <div class="select is-fullwidth is-medium">
                                                <select name="day" id="day">
                                                    <option selected value="select">Day</option>
                    HTML;
                echo $this->getDay();
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
                                            <option value="Jan">Jan</option>
                                            <option value="Feb">Feb</option>
                                            <option value="Mar">Mar</option>
                                            <option value="Apr">Apr</option>
                                            <option value="May">May</option>
                                            <option value="Jun">Jun</option>
                                            <option value="Jul">Jul</option>
                                            <option value="Aug">Aug</option>
                                            <option value="Sep">Sep</option>
                                            <option value="Oct">Oct</option>
                                            <option value="Nov">Nov</option>
                                            <option value="Dec">Dec</option>
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
                echo $this->getYear();
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
                        <label class="label" id="$nameKey"><b>$var</b></label>
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
                        <div class="columns">
                    HTML;
                for ($y = 0; $y < count($this->entValue[$i]['label']); ++$y) {
                    $label = empty($this->entValue[$i]['label'][$y]) ? '' : $this->entValue[$i]['label'][$y];
                    $name = empty($this->entValue[$i]['attribute'][$y]) ? '' : $this->entValue[$i]['attribute'][$y];
                    $placeholder = empty($this->entValue[$i]['placeholder'][$y]) ? '' : $this->entValue[$i]['placeholder'][$y];
                    $id = $name . '_id';
                    $error = $name . '_error';
                    $help = $name . '_help';
                    $cleanLabel = strtoupper($label);
                    $value = empty($this->entValue[$i]['value'][$y]) ? '' : $this->entValue[$i]['value'][$y];

                    $labelType = $this->entValue[$i]['inputType'][$y] ? $this->entValue[$i]['inputType'][$y] : '';
                    $icon = $this->entValue[$i]['icon'][$y] ?? '';

                    $hasIconLeft = (isset($this->entValue[$i]['icon'][$y]) ? 'has-icons-left' : '');
                    $hasImg = ($this->entValue[$i]['img'][$y] ?? '');

                    if ($labelType === 'select') {
                        echo <<<HTML
                            <div class="field $name" id="{$name}_div">
                                <label class="label is-medium" id="$name"><b>$cleanLabel</b></label>
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
                                    <input class="input $name input is-medium" id="{$name}_id" type="text" placeholder="$cleanLabel">
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

                            foreach ($decide as $value=> $option) {
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
                    } else {
                        echo <<<HTML
                            <div class="field $name" id="{$name}_div">
                                <label class="label is-medium" id="$name"><b>$cleanLabel</b></label>
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
                    $id = $name . '_id';
                    $error = $name . '_error';
                    $help = $name . '_help';
                    $cleanLabel = strtoupper($label);
                    $icon = $this->entValue[$i]['icon'][$y];
                    echo <<<HTML
                        <div class="field" id="{$name}_div">
                            <label class="label is-medium" id="$name"><b>$cleanLabel</b></label>
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
            } elseif ($this->entValue[$i] === 'captcha') {
                echo sprintf('<div class="g-recaptcha" data-sitekey="%s"></div>', getenv('RECAPTCHA_KEY'));
            } else {
                echo "Invalid form element type: {$this->entValue[$i]}";
            }
        }
    }
}
