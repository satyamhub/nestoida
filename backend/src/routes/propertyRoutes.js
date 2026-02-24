import { Router } from 'express';
import multer from 'multer';
import {
  createProperty,
  getFeaturedProperties,
  getProperties,
  getPropertyById
} from '../controllers/propertyController.js';
import authMiddleware from '../middleware/authMiddleware.js';
import validateRequest from '../middleware/validateRequest.js';
import { createPropertyValidator } from '../validators/propertyValidators.js';

const router = Router();
const upload = multer({ dest: 'uploads/' });

router.get('/', getProperties);
router.get('/featured', getFeaturedProperties);
router.get('/:id', getPropertyById);
router.post(
  '/',
  authMiddleware,
  upload.array('images', 6),
  createPropertyValidator,
  validateRequest,
  createProperty
);

export default router;
