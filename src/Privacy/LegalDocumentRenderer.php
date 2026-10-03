<?php
declare(strict_types=1);

namespace Dendrila\Cms\Privacy;

final class LegalDocumentRenderer
{
    public const KINDS = ['legal_notice', 'privacy', 'cookies'];

    /**
     * @param array<string,mixed> $profile
     * @param array<string,mixed> $consentManifest
     */
    public function render(string $kind, array $profile, array $consentManifest): string
    {
        self::assertKind($kind);
        $profile = LegalProfile::normalize($profile);
        $manifest = $this->manifest($consentManifest);

        return match ($kind) {
            'legal_notice' => $this->legalNotice($profile),
            'privacy' => $this->privacyPolicy($profile),
            'cookies' => $this->cookiePolicy($profile, $manifest),
        };
    }

    /**
     * @param array<string,mixed> $profile
     * @param array<string,mixed> $consentManifest
     * @return array{errors:list<string>,warnings:list<string>}
     */
    public function readiness(string $kind, array $profile, array $consentManifest): array
    {
        self::assertKind($kind);
        $profile = LegalProfile::normalize($profile);
        $manifest = $this->manifest($consentManifest);
        $errors = [];
        $warnings = [];

        if ($kind === 'legal_notice') {
            $editorName = $profile['site_editor_name'] !== '' ? $profile['site_editor_name'] : $profile['controller_name'];
            $editorAddress = $profile['site_editor_address'] !== '' ? $profile['site_editor_address'] : $profile['controller_address'];
            if ($editorName === '') {
                $errors[] = 'éditeur du site';
            }
            if ($editorAddress === '') {
                $errors[] = 'adresse de l’éditeur';
            }
            if ($profile['public_contact_email'] === '') {
                $errors[] = 'e-mail public de l’éditeur';
            }
            if (in_array($profile['entity_type'], ['company', 'individual'], true)
                && $profile['public_contact_phone'] === '') {
                $errors[] = 'téléphone public';
            }
            if (in_array($profile['entity_type'], ['company', 'individual'], true)
                && $profile['company_siren'] === '' && $profile['company_siret'] === ''
                && $profile['company_registration_number'] === '') {
                $errors[] = 'identifiant d’immatriculation';
            }
            if ($profile['publication_director'] === '') {
                $errors[] = 'responsable / directeur de la publication';
            }
            if ($profile['host_name'] === '') {
                $errors[] = 'nom de l’hébergeur';
            }
            if ($profile['host_address'] === '') {
                $errors[] = 'adresse de l’hébergeur';
            }
            if ($profile['host_phone'] === '') {
                $errors[] = 'téléphone de l’hébergeur';
            }
        }

        if ($kind === 'privacy') {
            if ($profile['controller_name'] === '') {
                $errors[] = 'responsable du traitement';
            }
            if ($profile['privacy_contact'] === '') {
                $errors[] = 'contact permettant d’exercer les droits';
            }
            if ($profile['dpo_designated'] === 'yes' && $profile['dpo_contact'] === '') {
                $errors[] = 'coordonnées du DPO/DPD désigné';
            }
            if ($profile['treatments'] === []) {
                $errors[] = 'au moins un traitement de données';
            }
            foreach ($profile['treatments'] as $index => $row) {
                $label = 'traitement ' . ($index + 1);
                if ($row['purpose'] === '') {
                    $errors[] = "{$label} : finalité";
                }
                if ($row['data_categories'] === '') {
                    $errors[] = "{$label} : catégories de données";
                }
                if ($row['legal_basis'] === 'unknown') {
                    $errors[] = "{$label} : base légale";
                }
                if ($row['legal_basis'] === 'legitimate_interest' && $row['basis_detail'] === '') {
                    $errors[] = "{$label} : intérêt légitime poursuivi";
                }
                if ($row['recipients'] === '') {
                    $errors[] = "{$label} : destinataires";
                }
                if ($row['retention'] === '') {
                    $errors[] = "{$label} : durée de conservation";
                }
                if (in_array($row['mandatory'], ['contract', 'law'], true) && $row['consequences'] === '') {
                    $errors[] = "{$label} : conséquence si la donnée obligatoire n’est pas fournie";
                }
            }
            if ($profile['representative_applicable'] === 'yes'
                && ($profile['controller_representative'] === '' || $profile['controller_representative_contact'] === '')) {
                $errors[] = 'représentant UE/EEE déclaré : identité et coordonnées';
            }
            if (in_array($profile['collection_mode'], ['indirect', 'both'], true)
                && $profile['indirect_services'] === []
                && ($profile['indirect_categories'] === '' || $profile['indirect_sources'] === '')) {
                $errors[] = 'données reçues indirectement : provenance';
            }
            if ($profile['transfers_status'] === 'yes' && $profile['transfer_destinations'] === '') {
                $errors[] = 'transfert hors UE/EEE déclaré : destination';
            }
            if ($profile['automated_decision'] === 'yes' && $profile['automated_details'] === '') {
                $errors[] = 'décision automatisée déclarée : explication';
            }
            if ($profile['special_categories'] === 'yes' && $profile['special_categories_basis'] === '') {
                $errors[] = 'données sensibles déclarées : cadre prévu';
            }
            if ($profile['criminal_data'] === 'yes' && $profile['criminal_data_basis'] === '') {
                $errors[] = 'données pénales déclarées : cadre prévu';
            }
            if ($profile['minors_data'] === 'yes' && $profile['minors_details'] === '') {
                $errors[] = 'données de mineurs déclarées : modalités prévues';
            }
            if ($profile['email_pixels'] === 'yes' && $profile['email_pixel_purposes'] === '') {
                $errors[] = 'suivi d’ouverture des e-mails déclaré : finalité';
            }
            if ($profile['tracked_links'] === 'yes' && $profile['tracked_links_details'] === '') {
                $errors[] = 'suivi individualisé des clics déclaré : explication';
            }
        }

        $hasOptionalTracking = $manifest['services'] !== [] || $profile['cookie_nonessential'] === 'yes';
        if (in_array($kind, ['privacy', 'cookies'], true) && $hasOptionalTracking
            && $manifest['enabled'] !== true && $profile['cookie_consent_status'] !== 'yes') {
            $errors[] = 'traceurs facultatifs : choix avant activation';
        }
        if ($profile['high_risk_processing'] === 'yes'
            && in_array($profile['dpia_status'], ['unknown', 'to_do'], true)) {
            $warnings[] = 'Un traitement à risque élevé est déclaré : vérifier si une AIPD/DPIA est nécessaire ou à finaliser.';
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /** @param array<string,mixed> $p */
    private function legalNotice(array $p): string
    {
        if ($p['controller_name'] === '' && $p['site_name'] === '' && $p['site_editor_name'] === '') {
            return '';
        }
        $editorName = $p['site_editor_name'] !== '' ? $p['site_editor_name'] : $p['controller_name'];
        $editorAddress = $p['site_editor_address'] !== '' ? $p['site_editor_address'] : $p['controller_address'];

        $html = '<section class="dendrila-privacy-document dendrila-privacy-legal-notice">';
        $html .= '<h2>Mentions légales</h2>';
        if ($p['site_name'] !== '') {
            $html .= '<p><strong>Site :</strong> ' . self::e($p['site_name']);
            if ($p['site_url'] !== '') {
                $html .= ' — <a href="' . self::a($p['site_url']) . '">' . self::e($p['site_url']) . '</a>';
            }
            $html .= '</p>';
        }
        $html .= '<h3>Éditeur du site</h3>';
        if ($editorName !== '') {
            $html .= '<p><strong>Nom / raison sociale :</strong> ' . self::e($editorName) . '</p>';
        }
        foreach ([
            'company_legal_form' => 'Forme juridique',
            'company_capital' => 'Capital social',
        ] as $key => $label) {
            if ($p[$key] !== '') {
                $html .= '<p><strong>' . self::e($label) . ' :</strong> ' . self::e($p[$key]) . '</p>';
            }
        }
        if ($editorAddress !== '') {
            $html .= '<p><strong>Adresse :</strong> ' . self::multiline($editorAddress) . '</p>';
        }
        if ($p['company_siren'] !== '') {
            $html .= '<p><strong>SIREN :</strong> ' . self::e($p['company_siren']) . '</p>';
        }
        if ($p['company_siret'] !== '') {
            $html .= '<p><strong>SIRET :</strong> ' . self::e($p['company_siret']) . '</p>';
        }
        if ($p['company_registration_number'] !== '' && $p['company_siren'] === '' && $p['company_siret'] === '') {
            $label = $p['company_registration_label'] !== '' ? $p['company_registration_label'] : 'Identifiant d’immatriculation';
            $html .= '<p><strong>' . self::e($label) . ' :</strong> ' . self::e($p['company_registration_number']) . '</p>';
        }
        if ($p['company_registry'] !== '') {
            $html .= '<p><strong>Immatriculation :</strong> ' . self::e($p['company_registry']) . '</p>';
        }
        if ($p['company_vat'] !== '') {
            $html .= '<p><strong>N° TVA :</strong> ' . self::e($p['company_vat']) . '</p>';
        }
        if ($p['public_contact_email'] !== '' || $p['public_contact_phone'] !== '') {
            $contact = implode(' — ', array_values(array_filter([$p['public_contact_email'], $p['public_contact_phone']])));
            $html .= '<p><strong>Contact :</strong> ' . self::e($contact) . '</p>';
        }
        if ($p['publication_director'] !== '') {
            $html .= '<p><strong>Directeur de la publication :</strong> ' . self::e($p['publication_director']) . '</p>';
        }
        if ($p['regulated_activity_details'] !== '') {
            $html .= '<p><strong>Activité réglementée / autorité compétente :</strong> '
                . self::multiline($p['regulated_activity_details']) . '</p>';
        }
        if ($p['rep_idu'] !== '') {
            $html .= '<p><strong>Identifiant(s) REP / IDU :</strong> ' . self::e($p['rep_idu']) . '</p>';
        }
        if ($p['host_name'] !== '' || $p['host_address'] !== '' || $p['host_phone'] !== '') {
            $html .= '<h3>Hébergement</h3>';
            if ($p['host_name'] !== '') {
                $html .= '<p><strong>Hébergeur :</strong> ' . self::e($p['host_name']) . '</p>';
            }
            if ($p['host_address'] !== '') {
                $html .= '<p><strong>Adresse :</strong> ' . self::multiline($p['host_address']) . '</p>';
            }
            if ($p['host_phone'] !== '') {
                $html .= '<p><strong>Téléphone :</strong> ' . self::e($p['host_phone']) . '</p>';
            }
        }
        return $html . '</section>';
    }

    /** @param array<string,mixed> $p */
    private function privacyPolicy(array $p): string
    {
        $usesConsent = false;
        $usesLegitimate = false;
        foreach ($p['treatments'] as $row) {
            $usesConsent = $usesConsent || $row['legal_basis'] === 'consent';
            $usesLegitimate = $usesLegitimate || $row['legal_basis'] === 'legitimate_interest';
        }

        $html = '<section class="dendrila-privacy-document dendrila-privacy-policy">';
        $html .= '<h2>Protection des données personnelles</h2>';
        $html .= '<h3>Responsable du traitement</h3>';
        if ($p['controller_name'] !== '') {
            $html .= '<p><strong>' . self::e($p['controller_name']) . '</strong>';
            if ($p['controller_address'] !== '') {
                $html .= '<br>' . self::multiline($p['controller_address']);
            }
            $html .= '</p>';
        }
        if ($p['privacy_contact'] !== '') {
            $html .= '<p><strong>Contact pour exercer vos droits :</strong> ' . self::e($p['privacy_contact']) . '</p>';
        }
        if ($p['dpo_designated'] === 'yes' && $p['dpo_contact'] !== '') {
            $html .= '<p><strong>Délégué à la protection des données (DPO/DPD) :</strong> '
                . self::e($p['dpo_contact']) . '</p>';
        }
        if ($p['representative_applicable'] === 'yes') {
            $html .= '<p><strong>Représentant dans l’Union européenne / EEE :</strong> '
                . self::e($p['controller_representative']);
            if ($p['controller_representative_contact'] !== '') {
                $html .= '<br>' . self::multiline($p['controller_representative_contact']);
            }
            $html .= '</p>';
        }

        $html .= '<h3>Traitements de données</h3>';
        foreach ($p['treatments'] as $row) {
            $html .= '<div class="dendrila-privacy-treatment"><h4>' . self::e($row['purpose']) . '</h4><ul>';
            $html .= '<li><strong>Catégories de données :</strong> ' . self::e($row['data_categories']) . '</li>';
            $html .= '<li><strong>Base juridique :</strong> ' . self::e(self::basisLabel($row['legal_basis']));
            if ($row['basis_detail'] !== '') {
                $html .= ' — ' . self::e($row['basis_detail']);
            }
            $html .= '</li>';
            $html .= '<li><strong>Destinataires ou catégories de destinataires :</strong> '
                . self::e($row['recipients']) . '</li>';
            $html .= '<li><strong>Durée de conservation :</strong> ' . self::e($row['retention']) . '</li>';
            if ($row['mandatory'] !== 'unknown') {
                $html .= '<li><strong>Fourniture des données :</strong> ' . self::e(self::mandatoryLabel($row['mandatory']));
                if ($row['consequences'] !== '') {
                    $html .= ' — ' . self::e($row['consequences']);
                }
                $html .= '</li>';
            }
            $html .= '</ul></div>';
        }

        if (in_array($p['collection_mode'], ['indirect', 'both'], true)) {
            $html .= '<h3>Données obtenues indirectement</h3>';
            if ($p['indirect_services'] !== []) {
                foreach ($p['indirect_services'] as $service) {
                    $title = $service['label'] !== '' ? $service['label'] : 'Service tiers';
                    $html .= '<div class="dendrila-privacy-indirect-service"><h4>' . self::e($title) . '</h4>';
                    $html .= '<p><strong>Données concernées :</strong> ' . self::e($service['categories'])
                        . '<br><strong>Provenance :</strong> ' . self::e($service['source']);
                    if ($service['recipients'] !== '') {
                        $html .= '<br><strong>Destinataires :</strong> ' . self::e($service['recipients']);
                    }
                    $html .= '</p>';
                    if ($service['transfer_status'] === 'yes') {
                        $html .= '<p><strong>Transfert hors UE/EEE :</strong> '
                            . self::e($service['transfer_destinations']);
                        if ($service['transfer_mechanism'] !== '') {
                            $html .= '<br><strong>Encadrement :</strong> ' . self::e($service['transfer_mechanism']);
                        }
                        if ($service['transfer_safeguards'] !== '') {
                            $html .= '<br><strong>Garanties :</strong> ' . self::e($service['transfer_safeguards']);
                        }
                        $html .= '</p>';
                    }
                    $html .= '</div>';
                }
            } elseif ($p['indirect_categories'] !== '' || $p['indirect_sources'] !== '') {
                $html .= '<p><strong>Catégories concernées :</strong> ' . self::e($p['indirect_categories'])
                    . '<br><strong>Source ou provenance :</strong> ' . self::e($p['indirect_sources']) . '</p>';
            }
        }

        if ($p['mandatory_information'] !== '') {
            $html .= '<h3>Données obligatoires ou facultatives</h3><p>'
                . self::multiline($p['mandatory_information']) . '</p>';
        }
        if ($p['special_categories'] === 'yes') {
            $html .= '<h3>Catégories particulières de données</h3><p>Le traitement comprend des catégories particulières '
                . 'de données au sens de l’article 9 du RGPD. <strong>Condition supplémentaire renseignée :</strong> '
                . self::e($p['special_categories_basis']) . '.</p>';
        }
        if ($p['criminal_data'] === 'yes') {
            $html .= '<h3>Données relatives aux condamnations pénales et infractions</h3><p><strong>Encadrement renseigné :</strong> '
                . self::e($p['criminal_data_basis']) . '.</p>';
        }
        if ($p['minors_data'] === 'yes') {
            $html .= '<h3>Données concernant des mineurs</h3><p>' . self::multiline($p['minors_details']) . '</p>';
        }
        if ($p['transfers_status'] === 'yes') {
            $html .= '<h3>Transferts de données hors Espace économique européen</h3><p><strong>Destination(s) / destinataire(s) :</strong> '
                . self::e($p['transfer_destinations'])
                . '<br><strong>Encadrement du transfert :</strong> ' . self::e($p['transfer_mechanism']);
            if ($p['transfer_safeguards'] !== '') {
                $html .= '<br><strong>Accès aux garanties ou informations complémentaires :</strong> '
                    . self::e($p['transfer_safeguards']);
            }
            $html .= '</p>';
        } elseif ($p['transfers_status'] === 'no') {
            $html .= '<h3>Transferts hors Espace économique européen</h3><p>Les traitements décrits ci-dessus n’impliquent pas, '
                . 'selon les informations renseignées pour cette politique, de transfert de données hors de l’Espace économique européen.</p>';
        }
        if ($p['automated_decision'] === 'yes') {
            $html .= '<h3>Décision automatisée et profilage</h3><p>' . self::multiline($p['automated_details']) . '</p>';
            $html .= '<p>Lorsque l’article 22 du RGPD s’applique, les garanties prévues peuvent notamment inclure le droit '
                . 'd’obtenir une intervention humaine, d’exprimer son point de vue et de contester la décision.</p>';
        }

        $html .= '<h3>Vos droits</h3><p>Dans les conditions prévues par le RGPD, vous pouvez demander l’accès à vos données, '
            . 'leur rectification ou leur effacement, la limitation de leur traitement et, lorsque les conditions sont réunies, '
            . 'exercer votre droit à la portabilité et votre droit d’opposition.</p>';
        if ($usesLegitimate) {
            $html .= '<p>Lorsque le traitement repose sur un intérêt légitime, vous pouvez vous y opposer pour des raisons '
                . 'tenant à votre situation particulière, sous réserve des exceptions prévues par le RGPD.</p>';
        }
        if ($usesConsent) {
            $html .= '<p>Lorsque le traitement repose sur votre consentement, vous pouvez retirer celui-ci à tout moment, '
                . 'sans remettre en cause la licéité du traitement effectué avant ce retrait.</p>';
        }
        if ($p['privacy_contact'] !== '') {
            $html .= '<p>Pour exercer vos droits : <strong>' . self::e($p['privacy_contact']) . '</strong>.</p>';
        } elseif ($p['dpo_designated'] === 'yes' && $p['dpo_contact'] !== '') {
            $html .= '<p>Pour exercer vos droits, vous pouvez contacter le DPO/DPD : <strong>'
                . self::e($p['dpo_contact']) . '</strong>.</p>';
        }
        if ($p['supervisory_authority'] !== '') {
            $authority = self::e($p['supervisory_authority']);
            if ($p['supervisory_url'] !== '') {
                $authority = '<a href="' . self::a($p['supervisory_url']) . '">' . $authority . '</a>';
            }
            $html .= '<p>Vous disposez également du droit d’introduire une réclamation auprès de ' . $authority . '.</p>';
        }

        if ($p['cookie_nonessential'] === 'yes') {
            $html .= '<h3>Cookies et autres traceurs</h3>';
            if ($p['cookie_consent_status'] === 'yes') {
                $html .= '<p>Les traceurs qui nécessitent un consentement sont soumis au choix de l’utilisateur avant leur lecture '
                    . 'ou leur dépôt. Le refus est proposé avec une facilité équivalente à l’acceptation.</p>';
            } elseif ($p['cookie_consent_status'] === 'exempt_only') {
                $html .= '<p>Les traceurs actuellement déclarés sont présentés comme relevant uniquement d’usages exemptés de consentement ; '
                    . 'cette qualification doit rester vérifiée au regard de leur finalité et de leur configuration réelle.</p>';
            }
            if ($p['cookie_preferences'] !== '') {
                $html .= '<p><strong>Modifier ou retirer vos choix :</strong> ' . self::preferenceValue($p['cookie_preferences']) . '</p>';
            }
            if ($p['cookie_choice_retention'] !== '') {
                $html .= '<p><strong>Durée de conservation du choix :</strong> ' . self::e($p['cookie_choice_retention']) . '</p>';
            }
            if ($p['cookie_cross_device'] === 'yes' && $p['cookie_cross_device_explanation'] !== '') {
                $html .= '<p><strong>Portée des choix sur plusieurs appareils :</strong> '
                    . self::e($p['cookie_cross_device_explanation']) . '</p>';
            }
        }

        if ($p['email_pixels'] === 'yes' || $p['tracked_links'] === 'yes') {
            $html .= '<h3>Suivi dans les courriels</h3>';
            if ($p['email_pixels'] === 'yes') {
                $html .= '<p>Certains courriels peuvent contenir des pixels de suivi. <strong>Finalité(s) :</strong> '
                    . self::e($p['email_pixel_purposes']) . '.</p>';
                if ($p['email_pixel_status'] === 'consent') {
                    $html .= '<p>L’utilisation de ces pixels est déclarée comme soumise au consentement lorsque celui-ci est requis.</p>';
                } elseif ($p['email_pixel_status'] === 'exempt_delivery') {
                    $html .= '<p>L’éditeur indique utiliser uniquement des pixels dont l’exemption a été évaluée pour une finalité '
                        . 'strictement liée à la délivrabilité. Toute utilisation au-delà de cette finalité doit faire l’objet d’une nouvelle analyse.</p>';
                }
                if ($p['email_pixel_preferences'] !== '') {
                    $html .= '<p><strong>Gérer ce choix :</strong> ' . self::e($p['email_pixel_preferences']) . '</p>';
                }
            }
            if ($p['tracked_links'] === 'yes') {
                $html .= '<p><strong>Suivi individualisé des clics :</strong> '
                    . self::multiline($p['tracked_links_details']) . '</p>';
                $html .= '<p>Le cadre applicable au suivi des clics dépend du mécanisme technique et de la finalité réellement utilisés ; '
                    . 'cette qualification doit rester vérifiée séparément des règles propres aux pixels invisibles.</p>';
            }
        }

        return $html . '</section>';
    }

    /**
     * @param array<string,mixed> $p
     * @param array{enabled:bool,retention_days:int,services:list<array{id:string,label:string,category:string}>} $manifest
     */
    private function cookiePolicy(array $p, array $manifest): string
    {
        $effectiveNonessential = $p['cookie_nonessential'] === 'unknown' && $manifest['services'] !== []
            ? 'yes' : $p['cookie_nonessential'];
        $effectiveConsent = $manifest['enabled'] ? 'yes' : $p['cookie_consent_status'];

        $html = '<section class="dendrila-privacy-document dendrila-privacy-cookie-policy">';
        $html .= '<h2>Politique de cookies et traceurs</h2>';
        if ($effectiveNonessential === 'yes') {
            $html .= '<p>Le site utilise des traceurs ou services qui ne sont pas strictement nécessaires à son fonctionnement.</p>';
            if ($effectiveConsent === 'yes') {
                $html .= '<p>Les services facultatifs concernés restent bloqués jusqu’à votre choix.</p>';
            } elseif ($effectiveConsent === 'exempt_only') {
                $html .= '<p>Certains traceurs sont utilisés uniquement dans les conditions présentées comme exemptées de consentement.</p>';
            }
        } elseif ($effectiveNonessential === 'no') {
            $html .= '<p>Le site n’utilise pas actuellement de traceur facultatif déclaré.</p>';
        } else {
            $html .= '<p>La présence de traceurs facultatifs n’a pas encore été qualifiée définitivement.</p>';
        }

        if ($p['cookie_preferences'] !== '') {
            $html .= '<p><strong>Modifier ou retirer vos choix :</strong> ' . self::preferenceValue($p['cookie_preferences']) . '</p>';
        } elseif ($manifest['enabled']) {
            $html .= '<p><button type="button" data-dendrila-consent-open="preferences">Gérer mes choix</button></p>';
        }
        $retention = $p['cookie_choice_retention'] !== ''
            ? $p['cookie_choice_retention']
            : ($manifest['enabled'] ? $manifest['retention_days'] . ' jours' : '');
        if ($retention !== '') {
            $html .= '<p><strong>Durée de mémorisation de vos choix :</strong> ' . self::e($retention) . '</p>';
        }
        if ($p['cookie_cross_device'] === 'yes' && $p['cookie_cross_device_explanation'] !== '') {
            $html .= '<p><strong>Plusieurs appareils :</strong> ' . self::multiline($p['cookie_cross_device_explanation']) . '</p>';
        }

        if ($manifest['services'] !== []) {
            $html .= '<h3>Services facultatifs détectés</h3><ul>';
            foreach ($manifest['services'] as $service) {
                $html .= '<li><strong>' . self::e($service['label']) . '</strong> — '
                    . self::e(self::categoryLabel($service['category'])) . '</li>';
            }
            $html .= '</ul>';
        }

        return $html . '</section>';
    }

    /** @param array<string,mixed> $manifest @return array{enabled:bool,retention_days:int,services:list<array{id:string,label:string,category:string}>} */
    private function manifest(array $manifest): array
    {
        $enabled = is_bool($manifest['enabled'] ?? null) ? $manifest['enabled'] : false;
        $retention = is_int($manifest['retention_days'] ?? null) ? $manifest['retention_days'] : 180;
        $services = [];
        foreach (is_array($manifest['services'] ?? null) ? $manifest['services'] : [] as $service) {
            if (!is_array($service) || !is_string($service['id'] ?? null) || !is_string($service['label'] ?? null)
                || !is_string($service['category'] ?? null)) {
                continue;
            }
            $services[] = [
                'id' => mb_substr($service['id'], 0, 64),
                'label' => mb_substr($service['label'], 0, 190),
                'category' => mb_substr($service['category'], 0, 64),
            ];
        }
        return [
            'enabled' => $enabled,
            'retention_days' => max(30, min(365, $retention)),
            'services' => array_slice($services, 0, 100),
        ];
    }

    private static function assertKind(string $kind): void
    {
        if (!in_array($kind, self::KINDS, true)) {
            throw new \InvalidArgumentException('Unknown privacy legal document kind.');
        }
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    private static function a(string $value): string
    {
        return self::e($value);
    }

    private static function multiline(string $value): string
    {
        return nl2br(self::e($value), false);
    }

    private static function preferenceValue(string $value): string
    {
        if (filter_var($value, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return '<a href="' . self::a($value) . '">ouvrir la page de gestion des cookies</a>';
        }
        return self::e($value);
    }

    private static function basisLabel(string $basis): string
    {
        return match ($basis) {
            'consent' => 'consentement',
            'contract' => 'exécution d’un contrat ou mesures précontractuelles',
            'legal_obligation' => 'obligation légale',
            'vital_interests' => 'sauvegarde des intérêts vitaux',
            'public_task' => 'mission d’intérêt public',
            'legitimate_interest' => 'intérêt légitime',
            default => 'base juridique à préciser',
        };
    }

    private static function mandatoryLabel(string $value): string
    {
        return match ($value) {
            'optional' => 'facultative',
            'contract' => 'nécessaire à l’exécution ou à la conclusion d’un contrat',
            'law' => 'requise par une obligation légale',
            default => 'à préciser',
        };
    }

    private static function categoryLabel(string $category): string
    {
        return match ($category) {
            'statistics' => 'mesure d’audience',
            'marketing' => 'marketing / suivi',
            'external_media' => 'contenu externe',
            'functional' => 'fonctionnalité',
            default => 'catégorie à vérifier',
        };
    }
}
