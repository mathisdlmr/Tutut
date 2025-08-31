<?php

return [
    'components' => [
        'tutor_rejected' => [
            'title' => 'Your application has been rejected.',
            'message' => 'You can modify your request and submit it again for a new evaluation.',
        ],
    ],
    'tables' => [
        'columns' => [
            'semaines_heures' => [
                'semaine' => 'Week',
                'no_results' => 'All hours for this month have already been entered.',
            ],
            'total_heures' => [
                'total' => 'Total',
            ],
        ],
    ],
    'become_tutor' => [
        // This page doesn't have any text that needs translation
    ],
    'calendar_manager' => [
        'previous_month' => 'Previous month',
        'next_month' => 'Next month',
        'holiday' => 'Holiday',
        'navigation_label' => 'Semester Calendar',
        'title' => 'Semester Calendar',
        'selected_date' => 'Date to modify',
        'schedule_modification' => 'Schedule Modification',
        'schedule_description' => 'Define schedule changes or holidays',
        'holiday_help_text' => 'Check this box for a holiday',
        'day_template' => 'Day Template',
        'days' => [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        ],
        'select_day' => 'Select a day',
        'save' => 'Save',
        'delete' => 'Delete',
        'date_out_of_range' => 'Date outside active semester',
        'date_must_be_between' => 'The selected date must be between :start and :end',
        'modification_saved' => 'Change saved',
        'modification_deleted' => 'Change deleted',
    ],
    'send_email' => [
        'preview' => 'Email preview',
        'send' => 'Send email',
        'save_template' => 'Save as template',
        'delete_template' => 'Delete template',
        'close' => 'Close',
    ],
    'settings_page' => [
        // This page doesn't have any text that needs translation
    ],
    'tutor_manage_uvs' => [
        'add' => 'Add',
        'update_languages' => 'Update my languages',
    ],
    'rgpd' => [
        'title' => 'GDPR Consent',
        'data_collection' => 'To enable the proper functioning of the application, we collect some of your personal data:',
        'optional_data' => 'Optionally for tutors:',
        'data_storage' => 'This data is securely stored on UTC servers and may potentially be exported by the Student Life Office to improve Tut\'ut',
        'accept' => 'I accept',
        'language' => 'Language',
        'french' => 'French',
        'english' => 'English',
    ],
]; 