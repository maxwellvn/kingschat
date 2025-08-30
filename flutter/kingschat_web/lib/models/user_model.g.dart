// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'user_model.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

UserModel _$UserModelFromJson(Map<String, dynamic> json) => UserModel(
  id: json['_id'] as String,
  userId: json['user_id'] as String,
  name: json['name'] as String,
  username: json['username'] as String,
  avatar: json['avatar'] as String?,
  bio: json['bio'] as String?,
  verified: json['verified'] as bool? ?? false,
);

Map<String, dynamic> _$UserModelToJson(UserModel instance) => <String, dynamic>{
  '_id': instance.id,
  'user_id': instance.userId,
  'name': instance.name,
  'username': instance.username,
  'avatar': instance.avatar,
  'bio': instance.bio,
  'verified': instance.verified,
};

UserProfile _$UserProfileFromJson(Map<String, dynamic> json) => UserProfile(
  user: UserModel.fromJson(json['user'] as Map<String, dynamic>),
  email: json['email'] == null
      ? null
      : EmailInfo.fromJson(json['email'] as Map<String, dynamic>),
);

Map<String, dynamic> _$UserProfileToJson(UserProfile instance) =>
    <String, dynamic>{'user': instance.user, 'email': instance.email};

EmailInfo _$EmailInfoFromJson(Map<String, dynamic> json) => EmailInfo(
  address: json['address'] as String,
  verified: json['verified'] as bool? ?? false,
);

Map<String, dynamic> _$EmailInfoToJson(EmailInfo instance) => <String, dynamic>{
  'address': instance.address,
  'verified': instance.verified,
};

KingsChatUserData _$KingsChatUserDataFromJson(Map<String, dynamic> json) =>
    KingsChatUserData(
      profile: UserProfile.fromJson(json['profile'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$KingsChatUserDataToJson(KingsChatUserData instance) =>
    <String, dynamic>{'profile': instance.profile};

AuthTokens _$AuthTokensFromJson(Map<String, dynamic> json) => AuthTokens(
  accessToken: json['accessToken'] as String,
  refreshToken: json['refreshToken'] as String?,
  expiresAt: (json['expiresAt'] as num?)?.toInt(),
);

Map<String, dynamic> _$AuthTokensToJson(AuthTokens instance) =>
    <String, dynamic>{
      'accessToken': instance.accessToken,
      'refreshToken': instance.refreshToken,
      'expiresAt': instance.expiresAt,
    };
