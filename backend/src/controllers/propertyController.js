import Property from '../models/Property.js';

export const getProperties = async (req, res, next) => {
  try {
    const { type, minPrice, maxPrice, gender, sector, amenities } = req.query;
    const query = {};

    if (type) query.type = type;
    if (gender) query.gender = gender;
    if (sector) query.sector = sector;

    if (minPrice || maxPrice) {
      query.price = {};
      if (minPrice) query.price.$gte = Number(minPrice);
      if (maxPrice) query.price.$lte = Number(maxPrice);
    }

    if (amenities) {
      const list = amenities.split(',');
      query.amenities = { $all: list };
    }

    const properties = await Property.find(query)
      .populate('createdBy', 'name email role')
      .sort({ createdAt: -1 });

    return res.json(properties);
  } catch (error) {
    return next(error);
  }
};

export const getFeaturedProperties = async (_req, res, next) => {
  try {
    const properties = await Property.find({ featured: true }).limit(6).sort({ createdAt: -1 });
    return res.json(properties);
  } catch (error) {
    return next(error);
  }
};

export const getPropertyById = async (req, res, next) => {
  try {
    const property = await Property.findById(req.params.id).populate('createdBy', 'name email role');
    if (!property) {
      return res.status(404).json({ message: 'Property not found' });
    }
    return res.json(property);
  } catch (error) {
    return next(error);
  }
};

export const createProperty = async (req, res, next) => {
  try {
    const amenities = Array.isArray(req.body.amenities)
      ? req.body.amenities
      : req.body.amenities
      ? [req.body.amenities]
      : [];

    const images = req.files?.map((file) => file.path) || [];

    const property = await Property.create({
      ...req.body,
      amenities,
      images,
      createdBy: req.user.id
    });

    return res.status(201).json(property);
  } catch (error) {
    return next(error);
  }
};
