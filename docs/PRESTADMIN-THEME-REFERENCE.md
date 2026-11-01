# Thème Très minimal — référence de site de services (Prestadmin)

L'écart avec la présentation réelle de Prestadmin a été vérifié sur le dépôt `prestadmin57-site` : bandeau éditorial avec photo et deux actions, deux encarts importants distincts, quatre offres, bénéfices, présentation illustrée, appel final et navigation mobile. Le thème Dendrila `ultra-minimal` → design kit `very-minimal` traduit désormais **cette structure, pas l'identité privée d'un client**.

- Photo locale de bureau fournie automatiquement sur la page d'accueil tant qu'aucune image de marque n'est configurée ; la photo choisie par l'éditeur reste prioritaire. Image décorative avec alternative vide pour ne pas répéter la présentation.
- L'initiale du site devient un monogramme rond, sauf si un véritable logo a été fourni.
- Deux cartes forment des encarts de mise en avant pleine largeur, quatre cartes composent une grille de prestations, cinq informations forment des bénéfices légers. Ces différences sont déterminées par les **nombres d'éléments éditables**, sans attribut ou nom Prestadmin dans le HTML.
- Le thème gère la photo de présentation, les cartes entièrement cliquables quand elles ont une destination, les états clavier, les écrans étroits et le mouvement réduit.
- Les contenus installés restent des exemples génériques. Prestadmin doit conserver ses véritables textes, liens /projet/, facturation et RGPD dans les données de son site, **pas dans ce thème**.

Cette étape améliore le kit natif et les démonstrations. Elle ne publie ni ne migre `prestadmin-test.lepotager.org`. Le test Prestadmin devra être actualisé depuis une version validée du CMS, ses paramètres visuels bordeaux configurés et sa navigation métier vérifiée. Les fonctionnalités réelles de facturation, paiement et parcours guidé ne sont pas créées par le seul CSS.

Le CSS livré dans `public/design-kits/themes/very-minimal/theme.css` inclut déjà des adaptations natives accumulées dans Dendrila après l'instantané historique du catalogue `dendrila-design-kits`. Son empreinte dans `bundle-lock.json` est actualisée pour les tests, tandis que le SHA de provenance historique reste inchangé ; un réalignement complet du paquet amont reste à effectuer avant une release du design-kit autonome.
