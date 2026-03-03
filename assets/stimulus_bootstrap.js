import { startStimulusApp } from '@symfony/stimulus-bundle';
import FormCollectionController from './controllers/form_collection_controller.js';

const app = startStimulusApp();
app.register('form-collection', FormCollectionController);
