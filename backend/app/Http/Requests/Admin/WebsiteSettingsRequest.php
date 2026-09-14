<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class WebsiteSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Unchecked HTML checkboxes are not sent. Default here so validation never
     * treats maintenance mode as "missing" (avoids a confusing "required" error).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'maintenance_mode' => $this->boolean('maintenance_mode'),
        ]);
    }

    public function rules(): array
    {
        return [
            'site_name' => 'required|string|max:255',
            'site_description' => 'nullable|string|max:1000',
            'site_email' => 'nullable|email|max:255',
            'site_phone' => 'nullable|string|max:50',
            'site_address' => 'nullable|string|max:500',
            'social_facebook' => 'nullable|url|max:255',
            'social_twitter' => 'nullable|url|max:255',
            'social_instagram' => 'nullable|url|max:255',
            'social_linkedin' => 'nullable|url|max:255',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:500',
            'google_analytics' => 'nullable|string|max:500',
            'maintenance_mode' => 'boolean',
            'maintenance_message' => 'nullable|string|max:1000',
            'site_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'site_favicon' => 'nullable|image|mimes:ico,png,jpg,jpeg|max:1024',
        ];
    }

    public function messages(): array
    {
        return [
            'site_name.required' => __('admin.vendor.website_settings.validation.site_name_required'),
            'site_name.max' => __('admin.vendor.website_settings.validation.site_name_max'),
            'site_description.max' => __('admin.vendor.website_settings.validation.site_description_max'),
            'site_email.email' => __('admin.vendor.website_settings.validation.site_email_email'),
            'site_email.max' => __('admin.vendor.website_settings.validation.site_email_max'),
            'site_phone.max' => __('admin.vendor.website_settings.validation.site_phone_max'),
            'site_address.max' => __('admin.vendor.website_settings.validation.site_address_max'),
            'social_facebook.url' => __('admin.vendor.website_settings.validation.social_facebook_url'),
            'social_twitter.url' => __('admin.vendor.website_settings.validation.social_twitter_url'),
            'social_instagram.url' => __('admin.vendor.website_settings.validation.social_instagram_url'),
            'social_linkedin.url' => __('admin.vendor.website_settings.validation.social_linkedin_url'),
            'meta_title.max' => __('admin.vendor.website_settings.validation.meta_title_max'),
            'meta_description.max' => __('admin.vendor.website_settings.validation.meta_description_max'),
            'meta_keywords.max' => __('admin.vendor.website_settings.validation.meta_keywords_max'),
            'google_analytics.max' => __('admin.vendor.website_settings.validation.google_analytics_max'),
            'maintenance_mode.boolean' => __('admin.vendor.website_settings.validation.maintenance_mode_boolean'),
            'maintenance_message.max' => __('admin.vendor.website_settings.validation.maintenance_message_max'),
            'site_logo.image' => __('admin.vendor.website_settings.validation.site_logo_image'),
            'site_logo.mimes' => __('admin.vendor.website_settings.validation.site_logo_mimes'),
            'site_logo.max' => __('admin.vendor.website_settings.validation.site_logo_max'),
            'site_favicon.image' => __('admin.vendor.website_settings.validation.site_favicon_image'),
            'site_favicon.mimes' => __('admin.vendor.website_settings.validation.site_favicon_mimes'),
            'site_favicon.max' => __('admin.vendor.website_settings.validation.site_favicon_max'),
        ];
    }
}
