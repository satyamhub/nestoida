import { body } from 'express-validator';

export const createPropertyValidator = [
  body('title').trim().notEmpty().withMessage('Title is required'),
  body('type').isIn(['Hostel', 'PG', 'Flat']).withMessage('Invalid property type'),
  body('sector').trim().notEmpty().withMessage('Sector is required'),
  body('price').isFloat({ min: 0 }).withMessage('Price must be a positive number'),
  body('gender').optional().isIn(['Any', 'Male', 'Female']),
  body('description').trim().notEmpty().withMessage('Description is required'),
  body('contactNumber').trim().notEmpty().withMessage('Contact number is required')
];
