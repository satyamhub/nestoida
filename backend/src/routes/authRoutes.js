import { Router } from 'express';
import { login, signup } from '../controllers/authController.js';
import { loginValidator, signupValidator } from '../validators/authValidators.js';
import validateRequest from '../middleware/validateRequest.js';

const router = Router();

router.post('/signup', signupValidator, validateRequest, signup);
router.post('/login', loginValidator, validateRequest, login);

export default router;
