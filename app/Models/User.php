<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * الخصائص التي يمكن تعبئتها جماعياً.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        "name",
        "email",
        "password",
        "phone",
        "type",   // landlord, renter, admin
        "uid",    // معرف فريد خارجي إذا لزم
    ];

    /**
     * الخصائص المخفية عند التحويل إلى JSON
     *
     * @var array<int, string>
     */
    protected $hidden = [
        "password",
        "remember_token",
    ];

    /**
     * تحويل بعض الخصائص تلقائياً
     *
     * @var array<string, string>
     */
    protected $casts = [
        "email_verified_at" => "datetime",
        "password" => "hashed",
    ];

    /**
     * JWT: استرجاع معرف المستخدم.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * JWT: أي خصائص مخصصة إضافية
     *
     * @return array
     */
    public function getJWTCustomClaims(): array
    {
        return [];
    }

    /**
     * العلاقة مع الشقق الخاصة بالمالك
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function apartments(): HasMany
    {
        return $this->hasMany(Apartment::class, "landlord_id");
    }

    /**
     * العلاقة مع المفضلة
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * العلاقة مع وثائق التحقق الخاصة بالمالك
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function verificationDocuments(): HasMany
    {
        return $this->hasMany(VerificationDocument::class, 'user_id', 'id');
    }

    /**
     * تحقق إذا كان المالك تم التحقق منه
     *
     * @return bool
     */
    public function isVerified(): bool
    {
        return $this->verificationDocuments()
                    ->where('status', 'approved')
                    ->exists();
    }

    /**
     * علاقة لإحصاء الوثائق المعلقة
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function pendingVerificationDocuments(): HasMany
    {
        return $this->verificationDocuments()->where('status', 'pending');
    }

    /**
     * علاقة لإحصاء الوثائق المرفوضة
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function rejectedVerificationDocuments(): HasMany
    {
        return $this->verificationDocuments()->where('status', 'rejected');
    }
}