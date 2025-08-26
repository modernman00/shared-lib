<?php

namespace Src;

use InvalidArgumentException;

class FormBuilder
{
    private array $entKey;
    private string $token;
    private array $entValue;
    private int $entCount;
    private string $framework;
    private array $config;
    private array $question;

    /**
     * Constructor for the FormBuilder.
     *
     * @param array $question The form question array.
     * @param string $framework The CSS framework ('bootstrap' or 'bulma').
     */
    public function __construct(array $question, string $framework = 'bootstrap')
    {
        $this->framework = $framework;
        $this->question = $question;
        $this->token = urlencode(base64_encode(random_bytes(32)));
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

        $this->config = $this->getFrameworkConfig();
    }

    /**
     * Get framework-specific configuration.
     *
     * @return array
     */
    private function getFrameworkConfig(): array
    {
        $bootstrapConfig = [
            'input_class' => 'form-control',
            'group_class' => 'mb-3',
            'label_class' => 'form-label',
            'select_class' => 'form-select form-select-lg mb-3',
            'error_class' => 'form-text text-danger',
            'help_class' => 'form-text text-muted',
            'button_class' => 'btn btn-primary',
            'input_group_class' => 'input-group',
            'input_group_prepend' => 'input-group-prepend',
            'input_group_append' => 'input-group-append',
            'checkbox_class' => 'form-check-input',
            'checkbox_label_class' => 'form-check-label',
            'radio_class' => 'form-check',
            'textarea_style' => '',
            'container_class' => 'row gx-4', // For mixed, select-many
            'column_class' => 'col',
            'error_container_class' => 'alert alert-danger',
            'error_container_style' => 'noDisplay',
            'select_icon_wrapper' => '',
            'button_size_class' => 'btn-lg',
        ];

        $bulmaConfig = [
            'input_class' => 'input',
            'group_class' => 'field',
            'label_class' => 'label is-medium',
            'select_class' => 'select is-medium',
            'error_class' => 'help is-danger',
            'help_class' => 'help',
            'button_class' => 'button is-success',
            'input_group_class' => 'control',
            'input_group_prepend' => 'control has-icons-left',
            'input_group_append' => 'control has-icons-right',
            'checkbox_class' => 'checkbox',
            'checkbox_label_class' => 'label',
            'radio_class' => 'radio',
            'textarea_style' => 'style="width: 100%; height: 100px; padding: 12px 20px; box-sizing: border-box; border: 2px solid #ccc; border-radius: 4px; background-color: #f8f8f8; font-size: 16px;"',
            'container_class' => 'columns', // For mixed, select-many
            'column_class' => 'column',
            'error_container_class' => 'notification',
            'error_container_style' => 'style="display: none;"',
            'select_icon_wrapper' => 'select is-fullwidth is-medium',
            'button_size_class' => 'is-large is-fullwidth',
        ];

        return $this->framework === 'bulma' ? $bulmaConfig : $bootstrapConfig;
    }

    /**
     * Generates an array of days.
     *
     * @return array
     */
    private function createDay(): array
    {
        return range(1, 31);
    }

    /**
     * Generates an array of months.
     *
     * @return array
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
     * @return array
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
     * Generates the form HTML.
     */
    public function genForm(): void
    {
        for ($i = 0; $i < $this->entCount; ++$i) {
            $value = isset($_POST['submit']) ? ($_POST[$this->entKey[$i]] ?? '') : '';
            $var = strtoupper(preg_replace('/[^0-9A-Za-z@.]/', ' ', $this->entKey[$i]));
            $nameKey = $this->entKey[$i];

            $type = is_array($this->entValue[$i]) ? $this->entValue[$i][0] : $this->entValue[$i];
            $method = 'render' . ucfirst($type);
            if (method_exists($this, $method)) {
                $this->$method($nameKey, $var, $value, is_array($this->entValue[$i]) ? $this->entValue[$i] : []);
            } else {
                echo "Invalid form element type: {$type}";
            }
        }
    }

    private function renderText(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        echo <<<HTML
            <div class="{$config['group_class']} $nameKey" id="{$nameKey}_div">
                <label class="{$config['label_class']}" for="$nameKey"><b>$var</b></label>
                <div class="{$config['input_group_class']}">
                    <input type="text" class="{$config['input_class']}" autocomplete="new-$nameKey" placeholder="PLEASE ENTER YOUR $var" name="$nameKey" id="$nameKey" value="$value" required>
                </div>
                <p class="{$config['help_class']}" id="{$nameKey}_help"></p>
                <p class="{$config['error_class']}" id="{$nameKey}_error"></p>
            </div>
        HTML;
    }

    private function renderTextIcon(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        $fontAwesome = $data[1] ?? '<i class="fas fa-user"></i>';
        echo <<<HTML
            <div class="{$config['group_class']}">
                <label class="{$config['label_class']}" for="$nameKey"><b>$var</b></label>
                <div class="{$config['input_group_prepend']}">
                    <input type="text" class="{$config['input_class']}" autocomplete="new-$nameKey" placeholder="$var" required name="$nameKey" id="$nameKey" value="$value">
                    <span class="icon is-small is-left">$fontAwesome</span>
                    <span class="icon is-small is-right"><i class="fas fa-check fa-xs"></i></span>
                </div>
                <p class="{$config['help_class']}" id="{$nameKey}_help"></p>
                <p class="{$config['error_class']}" id="{$nameKey}_error"></p>
            </div>
        HTML;
    }

    private function renderInteger(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        echo <<<HTML
            <div class="{$config['group_class']}">
                <label class="{$config['label_class']}" for="$nameKey"><b>$var</b></label>
                <div class="{$config['input_group_class']}">
                    <input type="number" class="{$config['input_class']}" autocomplete="new-$nameKey" placeholder="$var" required name="$nameKey" id="$nameKey" value="$value">
                </div>
                <p class="{$config['help_class']}" id="{$nameKey}_help"></p>
                <p class="{$config['error_class']}" id="{$nameKey}_error"></p>
            </div>
        HTML;
    }

    private function renderDate(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        echo <<<HTML
            <div class="{$config['group_class']} $nameKey" id="{$nameKey}_div">
                <label class="{$config['label_class']}" for="$nameKey"><b>$var</b></label>
                <div class="{$config['input_group_class']}">
                    <input type="date" class="{$config['input_class']}" autocomplete="new-$nameKey" placeholder="$var" name="$nameKey" id="$nameKey" value="$value">
                </div>
                <p class="{$config['help_class']}" id="{$nameKey}_help"></p>
                <p class="{$config['error_class']}" id="{$nameKey}_error"></p>
            </div>
        HTML;
    }

    private function renderSelect(string $nameKey, string $var, string $value, array $options): void
    {
        $config = $this->config;
        $selectWrapper = $this->framework === 'bulma' ? "<div class=\"{$config['select_class']}\">" : '';
        $selectWrapperEnd = $this->framework === 'bulma' ? '</div>' : '';
        echo <<<HTML
            <div class="{$config['group_class']}">
                <label class="{$config['label_class']}" for="$nameKey"><b>$var</b></label>
                <div class="{$config['input_group_class']}">
                    $selectWrapper
                    <select class="{$config['select_class']}" name="$nameKey" id="$nameKey">
                        <option value="" disabled selected>Select an option</option>
        HTML;
        foreach ($options as $option) {
            echo "<option value=\"$option\">$option</option>";
        }
        echo <<<HTML
                    </select>
                    $selectWrapperEnd
                </div>
                <p class="{$config['help_class']}" id="{$nameKey}_help"></p>
                <p class="{$config['error_class']}" id="{$nameKey}_error"></p>
            </div>
        HTML;
    }

    private function renderSelectIcon(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        $fontAwesome = $data[1] ?? '<i class="fas fa-user"></i>';
        $selectWrapper = $this->framework === 'bulma' ? "<div class=\"{$config['select_class']}\">" : '';
        $selectWrapperEnd = $this->framework === 'bulma' ? '</div>' : '';
        echo <<<HTML
            <div class="{$config['group_class']}">
                <label class="{$config['label_class']}" for="$nameKey"><b>$var</b></label>
                <div class="{$config['input_group_prepend']}">
                    $selectWrapper
                    <select class="{$config['select_class']}" name="$nameKey" id="$nameKey">
                        <option value="" disabled selected>Select an option</option>
        HTML;
        for ($y = 1; $y < count($data); ++$y) {
            echo "<option value=\"{$data[$y]}\">{$data[$y]}</option>";
        }
        echo <<<HTML
                    </select>
                    $selectWrapperEnd
                    <span class="icon is-small is-left">$fontAwesome</span>
                </div>
                <p class="{$config['help_class']}" id="{$nameKey}_help"></p>
                <p class="{$config['error_class']}" id="{$nameKey}_error"></p>
            </div>
        HTML;
    }

    private function renderTextarea(string $nameKey, string $var, string $value, array $data): void
    {


        $config = $this->config;
        $label = $data[1] ?? $var;
        echo <<<HTML
            <div class="{$config['group_class']}" id="{$nameKey}_div">
                <label class="{$config['label_class']}" for="$nameKey"><b>$label</b></label>
                <div class="{$config['input_group_class']}">
                    <textarea class="{$config['input_class']}" {$config['textarea_style']} autocomplete="new-$nameKey" placeholder="$label" name="$nameKey" id="$nameKey">$value</textarea>
                </div>
                <p class="{$config['help_class']}" id="{$nameKey}_help"></p>
                <p class="{$config['error_class']}" id="{$nameKey}_error"></p>
            </div>
        HTML;
    }

    private function renderEmail(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        $iconLeft = $this->framework === 'bulma' ? '<span class="icon is-small is-left"><i class="fas fa-envelope"></i></span>' : '';
        $iconRight = $this->framework === 'bulma' ? '<span class="icon is-small is-right"><i class="fas fa-check"></i></span>' : '';
        echo <<<HTML
            <div class="{$config['group_class']}">
                <label class="{$config['label_class']}" for="$nameKey"><b>$var</b></label>
                <div class="{$config['input_group_prepend']}">
                    <input type="email" class="{$config['input_class']} $nameKey is-medium" autocomplete="username" placeholder="" name="$nameKey" id="$nameKey" value="$value">
                    $iconLeft
                    $iconRight
                </div>
                <p class="{$config['help_class']}" id="{$nameKey}_help"></p>
                <p class="{$config['error_class']}" id="{$nameKey}_error"></p>
            </div>
        HTML;
    }

    private function renderPassword(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        $iconLeft = $this->framework === 'bulma' ? '<span class="icon is-small is-left"><i class="fas fa-lock"></i></span>' : '';
        $iconRight = $this->framework === 'bulma' ? '<span class="icon is-small is-right"><i class="fas fa-check"></i></span>' : '';
        echo <<<HTML
            <div class="{$config['group_class']}">
                <label class="{$config['label_class']}" for="$nameKey"><b>$var</b></label>
                <div class="{$config['input_group_prepend']}">
                    <input type="password" class="{$config['input_class']} $nameKey is-medium" autocomplete="new-password" placeholder="Enter your password" name="$nameKey" id="$nameKey" value="$value">
                    $iconLeft
                    $iconRight
                </div>
                <p class="{$config['help_class']}" id="{$nameKey}_help"></p>
                <p class="{$config['error_class']}" id="{$nameKey}_error"></p>
            </div>
        HTML;
    }

    private function renderCheckbox(string $nameKey, string $var, string $value, array $data): void
{
    $config = $this->config;
    $labelText = !empty($data) ? (string) $data : $var;

    echo <<<HTML
        <div class="{$config['group_class']}">
            <div class="{$config['input_group_class']}">
                <label class="{$config['checkbox_label_class']}">
                    <input type="checkbox" class="{$config['checkbox_class']}" name="$nameKey" id="$nameKey" checked>
                    $labelText
                </label>
            </div>
            <p class="{$config['error_class']}" id="{$nameKey}_error"></p>
        </div>
    HTML;
}


    private function renderButton(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        echo <<<HTML
            <div class="{$config['group_class']}">
                <div class="{$config['input_group_class']}">
                    <button name="button" id="button" type="button" class="{$config['button_class']} {$config['button_size_class']}">
                        {$nameKey}
                    </button>
                </div>
            </div>
        HTML;
    }

    private function renderButtonCaptcha(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        $js = $data['js'] ?? 'callback';
        $siteKey = $data['key'] ?? getenv('RECAPTCHA_KEY');
        $action = $data['action'] ?? 'submit';
        echo <<<HTML
            <div class="{$config['group_class']}">
                <div class="{$config['input_group_class']}">
                    <button type="button" id="button" class="{$config['button_class']} {$config['button_size_class']} g-recaptcha" data-sitekey="$siteKey" data-callback="$js" data-action="$action">
                        {$nameKey}
                    </button>
                </div>
            </div>
        HTML;
    }

    private function renderSubmit(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        echo <<<HTML
            <div class="{$config['group_class']}">
                <div class="{$config['input_group_class']}">
                    <button name="submit" id="submit" type="submit" class="{$config['button_class']} {$config['button_size_class']} submit">
                        Submit
                    </button>
                </div>
            </div>
        HTML;
    }

    private function renderToken(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        echo <<<HTML
            <div class="{$config['group_class']}">
                <div class="{$config['input_group_class']}">
                    <input type="hidden" class="{$config['input_class']}" id="token" name="token" value="{$this->token}">
                </div>
            </div>
        HTML;
    }

    private function renderBirthday(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        $divID = $data ?: $nameKey;
        echo <<<HTML
            <div class="{$config['group_class']}" id="$divID">
                <label class="{$config['label_class']}" for="$nameKey"><b>$var</b></label>
                <div class="{$config['container_class']}">
                    <div class="{$config['column_class']}">
                        <div class="{$config['input_group_class']}">
                            <div class="{$config['select_class']}">
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
                        <p class="{$config['error_class']}" id="day_error"></p>
                    </div>
                    <div class="{$config['column_class']}">
                        <div class="{$config['input_group_class']}">
                            <div class="{$config['select_class']}">
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
                        <p class="{$config['error_class']}" id="month_error"></p>
                    </div>
                    <div class="{$config['column_class']}">
                        <div class="{$config['input_group_class']}">
                            <div class="{$config['select_class']}">
                                <select name="year" id="year">
                                    <option selected value="select">Year</option>
        HTML;
        foreach ($this->createYear() as $year) {
            echo "<option

 value=\"$year\">$year</option>";
        }
        echo <<<HTML
                                </select>
                            </div>
                        </div>
                        <p class="{$config['error_class']}" id="year_error"></p>
                    </div>
                </div>
            </div>
        HTML;
    }

    private function renderSlider(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        $fAwesome = $data[1] ?? '<i class="fas fa-sliders-h"></i>';
        $sliderId = $data[2] ?? 'slider';
        $inputId = $data[3] ?? 'slider_input';
        echo <<<HTML
            <div class="{$config['group_class']}">
                <label class="{$config['label_class']}" for="$nameKey"><b>$var</b></label>
                <div class="{$config['container_class']}">
                    <div class="{$config['column_class']}">
                        <div id="$sliderId"></div>
                    </div>
                    <div class="{$config['column_class']}">
                        <div class="{$config['input_group_prepend']}">
                            <input type="number" class="{$config['input_class']} is-success" name="$inputId" id="$inputId" value="$value" readonly>
                            <span class="icon is-small is-left">$fAwesome</span>
                            <span class="icon is-small is-right"><i class="fas fa-check"></i></span>
                        </div>
                        <p class="{$config['help_class']}" id="{$inputId}_help"></p>
                        <p class="{$config['error_class']}" id="{$inputId}_error"></p>
                    </div>
                </div>
            </div>
        HTML;
    }

    private function renderMixed(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        echo "<div class=\"{$config['group_class']}\" id=\"$nameKey\">";
        echo "<div class=\"{$config['container_class']}\">";
        for ($y = 0; $y < count($data['label']); ++$y) {
            $label = $data['label'][$y] ?? '';
            $name = $data['attribute'][$y] ?? '';
            $placeholder = $data['placeholder'][$y] ?? '';
            $id = $name;
            $error = $name . '_error';
            $help = $name . '_help';
            $cleanLabel = strtoupper($label);
            $value = $data['value'][$y] ?? '';
            $labelType = $data['inputType'][$y] ?? 'text';
            $icon = $data['icon'][$y] ?? '';
            $hasIconLeft = $icon ? 'has-icons-left' : '';
            $hasImg = $data['img'][$y] ?? '';

            if ($labelType === 'select') {
                echo <<<HTML
                    <div class="{$config['group_class']} $name" id="{$name}_div">
                        <label class="{$config['label_class']}" for="$id"><b>$cleanLabel</b></label>
                        <div class="{$config['input_group_prepend']}">
                            <div class="{$config['select_class']}">
                                <select class="{$config['input_class']} is-medium" id="$id" name="$name">
                                    <option value="" disabled selected>Choose</option>
                HTML;
                if ($data['options'][$y]) {
                    foreach ($data['options'][$y] as $option) {
                        echo "<option value=\"$option\">$option</option>";
                    }
                }
                echo <<<HTML
                                </select>
                            </div>
                            <span class="icon is-small is-left">$icon</span>
                        </div>
                        <p class="{$config['help_class']}" id="$help"></p>
                        <p class="{$config['error_class']}" id="$error"></p>
                    </div>
                HTML;
            } elseif ($labelType === 'inputButton') {
                echo <<<HTML
                    <div class="{$config['group_class']} $name has-addons has-addons-left" id="{$name}_div">
                        <div class="{$config['input_group_prepend']} is-expanded $hasIconLeft">
                            <input type="text" class="{$config['input_class']} is-medium" id="$id" name="$name" placeholder="$cleanLabel">
                            <span class="icon is-small is-left">$icon</span>
                            <p class="{$config['help_class']}" id="$help"></p>
                        </div>
                        <div class="{$config['input_group_class']}">
                            <button class="{$config['button_class']} is-medium" id="{$name}_button">Search</button>
                        </div>
                    </div>
                HTML;
            } elseif ($labelType === 'cardSelect') {
                $cardClass = $this->framework === 'bulma' ? 'card h-100 hidden' : 'card h-100';
                $cardImage = $this->framework === 'bulma' ? '<div class="card-image"><figure class="image is-4by3"><img src="' . $hasImg . '" alt="Placeholder image"></figure></div>' : '<img src="' . $hasImg . '" class="card-img-top" alt="...">';
                $cardHeader = $this->framework === 'bulma' ? '<header class="card-header"><p class="card-header-title">' . $cleanLabel . '</p></header>' : '<div class="card-body"><h5 class="card-title">' . $cleanLabel . '</h5>';
                echo <<<HTML
                    <div class="{$config['column_class']} $name" id="{$name}_div">
                        <div class="$cardClass">
                            $cardImage
                            $cardHeader
                            <div class="card-content">
                                <div class="content">
                                    <select class="{$config['select_class']}" aria-label="Default" id="$id" name="$name">
                                        <option value="$value">Choose</option>
                HTML;
                if ($data['options'][$y]) {
                    foreach ($data['options'][$y] as $optionValue => $option) {
                        echo "<option value=\"$optionValue\">$option</option>";
                    }
                }
                echo <<<HTML
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                HTML;
            } elseif ($labelType === 'file') {
                $multiple = strpos($name, '[]') !== false ? 'multiple' : '';
                $attribute = str_replace(['[', ']'], '', $name);
                echo <<<HTML
                    <div class="{$config['group_class']} $attribute" id="{$attribute}_div">
                        <label class="{$config['label_class']}" for="$attribute"><b>$cleanLabel</b></label>
                        <div class="{$config['input_group_prepend']} is-expanded $hasIconLeft">
                            <input class="{$config['input_class']} $attribute is-medium" type="file" value="$value" maxlength="30" minlength="1" name="$name" id="$attribute" placeholder="$placeholder" autocomplete="$attribute" $multiple>
                            <span class="icon is-small is-left">$icon</span>
                            <p class="{$config['help_class']}" id="{$attribute}_help"></p>
                            <p class="{$config['error_class']}" id="{$attribute}_error"></p>
                        </div>
                    </div>
                HTML;
            } else {
                echo <<<HTML
                    <div class="{$config['group_class']} $name" id="{$name}_div">
                        <label class="{$config['label_class']}" for="$id"><b>$cleanLabel</b></label>
                        <div class="{$config['input_group_prepend']} is-expanded $hasIconLeft">
                            <input type="$labelType" class="{$config['input_class']} is-medium" value="$value" maxlength="30" minlength="1" name="$name" id="$id" placeholder="$placeholder" autocomplete="$name">
                            <span class="icon is-small is-left">$icon</span>
                            <p class="{$config['help_class']}" id="$help"></p>
                            <p class="{$config['error_class']}" id="$error"></p>
                        </div>
                    </div>
                HTML;
            }
        }
        echo "</div></div>";
    }

    private function renderMixedNested(string $nameKey, string $var, string $value, array $data): void
    {
        $this->renderMixed($nameKey, $var, $value, $data); // Same as mixed for now, can be customized if needed
    }

    private function renderSelectMany(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        echo "<div class=\"{$config['group_class']}\" id=\"$nameKey\">";
        echo "<div class=\"{$config['container_class']}\">";
        for ($y = 0; $y < count($data['label']); ++$y) {
            $options = $data['options'][$y] ?? [];
            $label = $data['label'][$y] ?? '';
            $name = $data['attribute'][$y] ?? '';
            $id = $name;
            $error = $name . '_error';
            $help = $name . '_help';
            $cleanLabel = strtoupper($label);
            $icon = $data['icon'][$y] ?? '';
            echo <<<HTML
                <div class="{$config['group_class']} $name" id="{$name}_div">
                    <label class="{$config['label_class']}" for="$id"><b>$cleanLabel</b></label>
                    <div class="{$config['input_group_prepend']}">
                        <div class="{$config['select_class']}">
                            <select class="{$config['input_class']} is-medium" id="$id" name="$name">
                                <option value="" disabled selected>Select an option</option>
            HTML;
            foreach ($options as $option) {
                echo "<option value=\"$option\">$option</option>";
            }
         echo <<<HTML
                            </select>
                        </div>
                        <span class="icon is-small is-left">$icon</span>
                        <span class="icon is-small is-right"><i class="fas fa-angle-down fasCol"></i></span>
                    </div>
                    <p class="{$config['help_class']}" id="$help"></p>
                    <p class="{$config['error_class']}" id="$error"></p>
                </div>
            HTML;
        }
        echo "</div></div>";
    }

    private function renderTitle(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        $titleClass = $this->framework === 'bulma' ? 'title is-3 is-spaced has-text-centered has-text-link is-primary the-title' : 'text-uppercase text-center text-primary';
        echo <<<HTML
            <hr><br>
            <h1 id="{$nameKey}1" class="$titleClass">$var</h1><br>
            <p class="{$config['help_class']}" id="{$nameKey}_help"></p>
        HTML;
    }

    private function renderSubtitle(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        $subtitleClass = $this->framework === 'bulma' ? 'subtitle has-text-centered is-primary' : 'text-center text-primary';
        echo <<<HTML
            <h3 class="$subtitleClass">$var</h3>
        HTML;
    }

    private function renderRadio(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        $labelValue = strtoupper($data[1] ?? $var);
        echo <<<HTML
            <hr><br>
            <div class="{$config['group_class']}">
                <div class="{$config['input_group_class']}">
                    <label class="{$config['radio_class']}">
                        <b class="h6">$labelValue</b>
                        <input type="radio" class="{$config['checkbox_class']}" name="$nameKey" value="{$data[2]}" id="{$nameKey}_yes">
                        {$data[2]}
                    </label>
                    <label class="{$config['radio_class']}">
                        <input type="radio" class="{$config['checkbox_class']}" name="$nameKey" value="{$data[3]}" id="{$nameKey}_no">
                        {$data[3]}
                    </label>
                    <p class="{$config['error_class']}" id="{$nameKey}_error"></p>
                </div>
            </div>
        HTML;
    }

    private function renderRadioComment(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        $labelValue = strtoupper($data[1] ?? $var);
        echo <<<HTML
            <hr><br>
            <div class="{$config['group_class']}">
                <div class="{$config['input_group_class']}">
                    <label class="{$config['radio_class']}">
                        <b class="h6">$labelValue</b>
                        <input type="radio" class="{$config['checkbox_class']}" name="$nameKey" value="{$data[2]}" id="{$nameKey}_yes"> {$data[2]}
                    </label>
                    <label class="{$config['radio_class']}">
                        <input type="radio" class="{$config['checkbox_class']}" name="$nameKey" value="{$data[3]}" id="{$nameKey}_no"> {$data[3]}
                    </label>
                    <p class="{$config['help_class']}" id="{$nameKey}_help"></p>
                    <p class="{$config['error_class']}" id="{$nameKey}_error"></p>
                    <textarea name="{$nameKey}Comment" id="{$nameKey}Comment" placeholder="Please add any further relevant comment especially if you selected No" class="{$config['input_class']}" {$config['textarea_style']}></textarea>
                </div>
            </div>
        HTML;
    }

    private function renderHr(string $nameKey, string $var, string $value, array $data): void
    {
        echo '<hr>';
    }

    private function renderBr(string $nameKey, string $var, string $value, string $data): void
    {
        echo '<br>';
    }

    private function renderLoader(string $nameKey, string $var, string $value, array $data): void
    {
        $style = $this->framework === 'bulma' ? 'style="display: none;"' : 'noDisplay';
        echo "<div id='setLoader' tabindex='-1' class='loader $style'></div>";
    }

    private function renderSetError(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        echo "<div class=\"{$config['error_container_class']} {$config['error_container_style']}\" id=\"$nameKey\"><p id=\"error\"></p></div>";
    }

    private function renderShowError(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        $style = $this->framework === 'bulma' ? 'style="display: none;"' : 'noDisplay';
        echo "<div id=\"setLoader\" tabindex=\"-1\" class=\"loader $style\"></div>
              <div class=\"{$config['error_container_class']} $style\" id=\"$nameKey\"><p id=\"error\"></p></div>";
    }

    private function renderCaptcha(string $nameKey, string $var, string $value, array $data): void
    {
        echo sprintf('<div class="g-recaptcha" data-sitekey="%s"></div>', getenv('RECAPTCHA_KEY'));
    }

    private function renderShowPassword(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        echo <<<HTML
            <label class="{$config['checkbox_label_class']}">
                <input type="checkbox" id="showPassword">
                Show Password
            </label><br>
        HTML;
    }

    private function renderEmpty(string $nameKey, string $var, string $value, array $data): void
    {
        echo '';
    }

    private function renderBlank(string $nameKey, string $var, string $value, array $data): void
    {
        $config = $this->config;
        echo "<div class=\"{$config['group_class']}\"></div>";
    }
}