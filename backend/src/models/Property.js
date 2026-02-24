import mongoose from 'mongoose';

const propertySchema = new mongoose.Schema(
  {
    title: { type: String, required: true, trim: true },
    type: { type: String, enum: ['Hostel', 'PG', 'Flat'], required: true },
    sector: { type: String, required: true },
    price: { type: Number, required: true, min: 0 },
    gender: { type: String, enum: ['Any', 'Male', 'Female'], default: 'Any' },
    amenities: [{ type: String }],
    images: [{ type: String }],
    description: { type: String, required: true },
    contactNumber: { type: String, required: true },
    featured: { type: Boolean, default: false },
    createdBy: { type: mongoose.Schema.Types.ObjectId, ref: 'User', required: true }
  },
  { timestamps: true }
);

const Property = mongoose.model('Property', propertySchema);
export default Property;
