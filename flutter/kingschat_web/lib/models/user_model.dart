import 'package:json_annotation/json_annotation.dart';

part 'user_model.g.dart';

@JsonSerializable()
class UserModel {
  @JsonKey(name: '_id')
  final String id;
  
  @JsonKey(name: 'user_id')
  final String userId;
  
  final String name;
  final String username;
  final String? avatar;
  final String? bio;
  final bool verified;
  
  UserModel({
    required this.id,
    required this.userId,
    required this.name,
    required this.username,
    this.avatar,
    this.bio,
    this.verified = false,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) => _$UserModelFromJson(json);
  Map<String, dynamic> toJson() => _$UserModelToJson(this);
}

@JsonSerializable()
class UserProfile {
  final UserModel user;
  final EmailInfo? email;
  
  UserProfile({
    required this.user,
    this.email,
  });

  factory UserProfile.fromJson(Map<String, dynamic> json) => _$UserProfileFromJson(json);
  Map<String, dynamic> toJson() => _$UserProfileToJson(this);
}

@JsonSerializable()
class EmailInfo {
  final String address;
  final bool verified;
  
  EmailInfo({
    required this.address,
    this.verified = false,
  });

  factory EmailInfo.fromJson(Map<String, dynamic> json) => _$EmailInfoFromJson(json);
  Map<String, dynamic> toJson() => _$EmailInfoToJson(this);
}

@JsonSerializable()
class KingsChatUserData {
  final UserProfile profile;
  
  KingsChatUserData({
    required this.profile,
  });

  factory KingsChatUserData.fromJson(Map<String, dynamic> json) => _$KingsChatUserDataFromJson(json);
  Map<String, dynamic> toJson() => _$KingsChatUserDataToJson(this);
}

@JsonSerializable()
class AuthTokens {
  final String accessToken;
  final String? refreshToken;
  final int? expiresAt;
  
  AuthTokens({
    required this.accessToken,
    this.refreshToken,
    this.expiresAt,
  });

  factory AuthTokens.fromJson(Map<String, dynamic> json) => _$AuthTokensFromJson(json);
  Map<String, dynamic> toJson() => _$AuthTokensToJson(this);
  
  bool get isExpired {
    if (expiresAt == null) return false;
    return DateTime.now().millisecondsSinceEpoch > expiresAt! * 1000;
  }
  
  bool needsRefresh({int bufferSeconds = 300}) {
    if (expiresAt == null) return false;
    final bufferTime = DateTime.now().millisecondsSinceEpoch + (bufferSeconds * 1000);
    return bufferTime > expiresAt! * 1000;
  }
}
