<?php

return [
    'components' => [
        'tutor_rejected' => [
            'title' => 'Votre candidature a été refusée.',
            'message' => 'Vous pouvez modifier votre demande et la soumettre à nouveau pour une nouvelle évaluation.',
        ],
    ],
    'tables' => [
        'columns' => [
            'semaines_heures' => [
                'semaine' => 'Semaine',
                'no_results' => 'Toutes les heures sur ce mois ont déjà été saisies.',
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
        'previous_month' => 'Mois précédent',
        'next_month' => 'Mois suivant',
        'holiday' => 'Férié',
        'navigation_label' => 'Calendrier du semestre',
        'title' => 'Calendrier du semestre',
        'selected_date' => 'Date à modifier',
        'schedule_modification' => 'Modification du planning',
        'schedule_description' => 'Définissez les modifications d\'emploi du temps ou les jours fériés',
        'holiday_help_text' => 'Cochez cette case pour un jour férié',
        'day_template' => 'Modèle de journée',
        'days' => [
            'monday' => 'Lundi',
            'tuesday' => 'Mardi',
            'wednesday' => 'Mercredi',
            'thursday' => 'Jeudi',
            'friday' => 'Vendredi',
            'saturday' => 'Samedi',
            'sunday' => 'Dimanche',
        ],
        'select_day' => 'Sélectionnez un jour',
        'save' => 'Enregistrer',
        'delete' => 'Supprimer',
        'date_out_of_range' => 'Date hors du semestre actif',
        'date_must_be_between' => 'La date sélectionnée doit être entre :start et :end',
        'modification_saved' => 'Modification enregistrée',
        'modification_deleted' => 'Modification supprimée',
    ],
    'send_email' => [
        'preview' => 'Aperçu du mail',
        'send' => 'Envoyer le mail',
        'save_template' => 'Enregistrer comme template',
        'delete_template' => 'Supprimer le template',
        'close' => 'Fermer',
    ],
    'settings_page' => [
        // This page doesn't have any text that needs translation
    ],
    'tutor_manage_uvs' => [
        'add' => 'Ajouter',
        'update_languages' => 'Mettre à jour mes langues',
    ],
    'rgpd' => [
        'title' => 'Consentement RGPD',
        'data_collection' => 'Pour permettre le bon fonctionnement de l\'application, nous collectons certaines de vos données personnelles :',
        'optional_data' => 'De manière optionnelle pour les tuteur.ice.s :',
        'data_storage' => 'Ces données sont stockées de façon sécurisée sur les serveurs de l\'UTC et peuvent potentiellement être exportées par le Bureau de Vie Etudiante afin d\'améliorer Tut\'ut',
        'accept' => 'J\'accepte',
        'language' => 'Langue',
        'french' => 'Français',
        'english' => 'Anglais',
    ],
]; 