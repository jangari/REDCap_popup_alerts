<?php namespace INTERSECT\PopupAlerts;

use \REDCap as REDCap;
use \Project as Project;
use ExternalModules\AbstractExternalModule;

class PopupAlerts extends \ExternalModules\AbstractExternalModule {

    function getTags($tags, $fields, $instruments) {
        // Thanks to Andy Martin
        // See https://community.projectredcap.org/questions/32001/custom-action-tags-or-module-parameters.html
        if (!class_exists('INTERSECT\PopupAlerts\ActionTagHelper')) include_once('classes/ActionTagHelper.php');
        $action_tag_results = ActionTagHelper::getActionTags($tags, $fields, $instruments);
        return $action_tag_results;
    }

    function redcap_survey_page($project_id, $record, $instrument) {

		// Collect project settings
		$settings = $this->getProjectSettings();
        
        // Halt if not enabled
        if (!$settings['popups-enabled']) return;
        
		$modalTitle = $settings['modal-title'] ?? $this->tt('modal-title-text');
        $okayBtnText = $settings['modal-okay-btn'] ?? $this->tt('modal-okay-btn-text');

		// Get all annotated fields
        $alertTag = "@ALERT";
        $annotatedFields = $this->getTags($alertTag, $fields=NULL, $instruments=$instrument);

        // Populate an array of fields and any titles
        $alertFields = [];
        if (!empty($annotatedFields[$alertTag]) && is_array($annotatedFields[$alertTag])) {
            foreach (array_keys($annotatedFields[$alertTag]) as $fieldName) {
                $title = trim($annotatedFields[$alertTag][$fieldName][0], '"');
                $title = empty($title) ? $modalTitle : $title;
                $alertFields[$fieldName] = [
                    'title' => $this->escape($title)
                ];
            };
        };

        // Halt if alertFields is empty
        if (empty($alertFields)) return;

        // Inject the alert modal HTML
        echo "<div class=\"modal fade\" id=\"alertModal\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"alertModalLabel\" aria-hidden=\"true\" data-backdrop=\"static\" data-keyboard=\"false\">
            <div class=\"modal-dialog\" role=\"document\">
                <div class=\"modal-content\">
                    <div class=\"modal-header\">
                        <h5 class=\"modal-title\" id=\"alertModalLabel\"></h5>
                    </div>
                    <div class=\"modal-body\">
                        <div class=\"alert-content\"></div>
                    </div>
                    <div class=\"modal-footer\">
                        <button type=\"button\" class=\"btn btn-primary\" id=\"alertModalOkayBtn\">Okay</button>
                    </div>
                </div>
            </div>
        </div>";
        echo "<script type='text/javascript'>
    var alertFields = " . json_encode($alertFields) . ";

    // Function to transform the descriptive text field into a Bootstrap modal alert
    function transformToAlert(element, alertTitle) {

        var fieldContent = element.find('.rich-text-field-label').html();
        
        // Check if the element has .rich-text-field-label class
        if (fieldContent) {
            fieldContent = element.find('.rich-text-field-label').html();
        } else {
            // Use textContent to get plain text
            fieldContent = \"<strong>\" + element[0].textContent.trim() + \"</strong>\";
        }

        // Hide the element from the DOM
        element.hide();

        // Set the title and content of the modal
        $('#alertModal .alert-content').html(fieldContent);
        $('#alertModal .modal-title').text(alertTitle);

        // Show the modal
        $('#alertModal').modal('show');
    }

    // Function to handle the visibility and transformation to alert
    function handleVisibilityChange(element, alertTitle) {
        return function(mutationsList) {
            for (let mutation of mutationsList) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                    if ($(element).is(':visible')) {
                        transformToAlert($(element), alertTitle);
                    }
                }
            }
        }
    }

    // Configuration for the observer
    var config = { attributes: true, attributeFilter: ['style'] };

    $(document).ready(function() {
    // Loop through all alertField elements, attach each to a DOM element and begin observing it
    for (var field in alertFields) {
        var alertTitle = alertFields[field].title;
        var fieldElement = $('#' + field + '-tr');

        if (fieldElement.length) {
            // Create an instance of MutationObserver for each field
            var observer = new MutationObserver(handleVisibilityChange(fieldElement[0], alertTitle));
            observer.observe(fieldElement[0], config);
        }
    }
});

    // Handle the dismissal of the modal when the Okay button is clicked
    $('#alertModalOkayBtn').click(function() {
        $('#alertModal').modal('hide');
    });
            </script>";
    }
}
